<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DownloadBastController extends Controller
{
    /**
     * BAST dibuat dari data serah-terima; boleh diunduh kalau user boleh melihat asetnya.
     */
    public function __invoke(int $assignment): Response
    {
        // Global scope serah-terima mengikuti hak lihat aset, jadi BAST aset yang tidak boleh dilihat terbaca 404.
        $first = Assignment::query()->with('asset.company')->findOrFail($assignment);

        Gate::authorize('view', $first->asset);

        // Satu BAST adalah satu dokumen: semua baris ikut, termasuk aset menempel dari kategori lain.
        $rows = Assignment::withoutGlobalScope('asset')
            ->with(['asset' => fn (BelongsTo $query) => $query->withoutGlobalScopes(), 'createdBy'])
            ->where('no_bast', $first->no_bast)
            ->orderBy('id')
            ->get();

        $company = $first->asset->company;

        return Pdf::loadView('pdf.bast', [
            'first' => $first,
            'rows' => $rows,
            'company' => $company,
            'kop' => Setting::get('kop_bast', null, $company->id),
            'logo' => $this->logoDataUri(Setting::get('logo', null, $company->id)),
        ])->setPaper('a4')->download(Str::of($first->no_bast)->replaceMatches('/[^A-Za-z0-9]+/', '-')->trim('-').'.pdf');
    }

    // Logo disematkan sebagai data URI supaya dompdf tidak perlu membaca file atau URL luar.
    private function logoDataUri(?string $path): ?string
    {
        $disk = Storage::disk(config('filament.default_filesystem_disk'));

        if (blank($path) || ! $disk->exists($path)) {
            return null;
        }

        return 'data:'.$disk->mimeType($path).';base64,'.base64_encode($disk->get($path));
    }
}
