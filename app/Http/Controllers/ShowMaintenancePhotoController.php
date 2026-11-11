<?php

namespace App\Http\Controllers;

use App\Models\Maintenance;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShowMaintenancePhotoController extends Controller
{
    /**
     * Foto kerusakan dari disk privat, hanya untuk yang boleh melihat perbaikannya.
     */
    public function __invoke(int $maintenance): StreamedResponse
    {
        // Global scope perbaikan mengikuti hak lihat aset, jadi aset yang tidak boleh dilihat terbaca 404.
        $maintenance = Maintenance::query()->findOrFail($maintenance);

        abort_if(blank($maintenance->foto), 404);
        Gate::authorize('view', $maintenance);

        return Storage::disk(Maintenance::FOTO_DISK)->response($maintenance->foto);
    }
}
