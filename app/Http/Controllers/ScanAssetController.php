<?php

namespace App\Http\Controllers;

use App\Filament\Karyawan\Pages\MyAssetDetail;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;

class ScanAssetController extends Controller
{
    /**
     * Tujuan QR di label: hanya mengarahkan ke panel yang sesuai, tanpa membaca data aset.
     * Detail selalu dibuka di dalam panel, jadi login, 2FA, dan hak lihat tetap berlaku.
     */
    public function __invoke(string $kode): RedirectResponse
    {
        $user = auth()->user();

        // Tamu masuk dulu, lalu kembali ke alamat ini dan diarahkan sesuai role.
        if ($user === null) {
            return redirect()->guest(route('filament.auth.auth.login'));
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
