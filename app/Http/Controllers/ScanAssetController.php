<?php

namespace App\Http\Controllers;

use App\Filament\Karyawan\Pages\MyAssetDetail;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ScanAssetController extends Controller
{
    /**
     * Tujuan QR di label: hanya mengarahkan ke panel yang sesuai, tanpa membaca data aset.
     * Detail selalu dibuka di dalam panel, jadi login, 2FA, dan hak lihat tetap berlaku.
     */
    public function __invoke(string $kode): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user === null) {
            return view('filament.scan', [
                'kode' => $kode,
                'adminUrl' => route('filament.admin.assets.scan', $kode),
                'employeeUrl' => MyAssetDetail::getUrl(['kode' => $kode], panel: 'karyawan'),
            ]);
        }

        if ($user->canAccessPanel(Filament::getPanel('admin'))) {
            return redirect()->route('filament.admin.assets.scan', $kode);
        }

        abort_unless($user->canAccessPanel(Filament::getPanel('karyawan')), 403);

        return redirect(MyAssetDetail::getUrl(['kode' => $kode], panel: 'karyawan'));
    }

    /**
     * Panel admin: cari aset lewat hak lihat user (perusahaan, kategori), lalu buka halaman detailnya.
     */
    public function admin(string $kode): RedirectResponse
    {
        $asset = Asset::query()->where('kode', $kode)->firstOrFail();

        return redirect(AssetResource::getUrl('view', ['record' => $asset], panel: 'admin'));
    }
}
