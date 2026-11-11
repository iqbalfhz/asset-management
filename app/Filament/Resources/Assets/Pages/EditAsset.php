<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use Filament\Resources\Pages\EditRecord;

// Tidak ada tombol hapus: aset yang tidak dipakai lagi melalui proses penghapusan (Fase 5).
class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;
}
