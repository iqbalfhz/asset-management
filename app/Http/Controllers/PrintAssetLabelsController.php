<?php

namespace App\Http\Controllers;

use App\Enums\LabelField;
use App\Enums\LabelSize;
use App\Filament\Resources\Assets\Actions\PrintLabelActions;
use App\Models\Asset;
use App\Models\Setting;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeCode128;

class PrintAssetLabelsController extends Controller
{
    /**
     * Halaman cetak label; hanya aset yang boleh dilihat user yang ikut tercetak.
     */
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Asset::class);

        $ids = collect(explode(',', (string) $request->query('aset')))
            ->map(fn (string $id): int => (int) $id)
            ->filter()
            ->unique()
            ->take(PrintLabelActions::MAX_LABELS);

        $assets = Asset::query()->with('company')->whereKey($ids)->orderBy('kode')->get();

        abort_if($assets->isEmpty(), 404);

        // Ukuran halaman cetak berlaku untuk satu dokumen, jadi diambil dari perusahaan aset pertama.
        $companyId = $assets->first()->company_id;
        $size = LabelSize::tryFrom((string) Setting::get('label_ukuran', LabelSize::Thermal50x25->value, $companyId)) ?? LabelSize::Thermal50x25;
        $fields = collect(Setting::get('label_isi', LabelField::defaults(), $companyId))
            ->map(fn (mixed $field): ?LabelField => $field instanceof LabelField ? $field : LabelField::tryFrom((string) $field))
            ->filter()
            ->values();

        return view('labels', [
            'size' => $size,
            'fields' => $fields,
            'labels' => $assets->map(fn (Asset $asset): array => [
                'asset' => $asset,
                'qr' => $fields->contains(LabelField::Qr) ? static::qrSvg(route('asset.scan', $asset->kode)) : null,
                'barcode' => $fields->contains(LabelField::Barcode) ? static::barcodeSvg($asset->kode) : null,
            ]),
        ]);
    }

    private static function qrSvg(string $url): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(256, 1), new SvgImageBackEnd)))
            ->writeString($url, ecLevel: ErrorCorrectionLevel::M());

        // Disisipkan langsung ke HTML, jadi deklarasi XML di awal dibuang.
        return (string) preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }

    private static function barcodeSvg(string $code): string
    {
        $barcode = (new TypeCode128)->getBarcode($code);
        $renderer = (new SvgRenderer)->setSvgType(SvgRenderer::TYPE_SVG_INLINE);

        // Tanpa menjaga rasio, supaya batang melebar mengikuti kotak label.
        return str_replace('<svg ', '<svg preserveAspectRatio="none" ', $renderer->render($barcode, $barcode->getWidth() * 2, 40));
    }
}
