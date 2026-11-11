<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Assets\Schemas\AssetForm;
use App\Models\Asset;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    private int $createdCount = 1;

    /**
     * Satu form bisa membuat beberapa aset; semuanya dibuat dalam satu transaksi supaya kodenya berurutan.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $count = max(1, (int) ($data['jumlah'] ?? 1));
        $serialNumbers = AssetForm::serialNumberLines($data['nomor_seri_banyak'] ?? null);
        $statusId = $data['status_id'];

        unset($data['jumlah'], $data['nomor_seri_banyak'], $data['status_id']);

        $first = DB::transaction(function () use ($data, $count, $serialNumbers, $statusId): Asset {
            $assets = collect(range(0, $count - 1))->map(function (int $index) use ($data, $count, $serialNumbers, $statusId): Asset {
                $asset = new Asset($data);
                $asset->status_id = $statusId;

                if ($count > 1) {
                    $asset->no_seri = $serialNumbers[$index] ?? null;
                }

                $asset->save();

                return $asset;
            });

            return $assets->first();
        });

        $this->createdCount = $count;

        return $first;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->createdCount > 1
            ? __(':count aset dibuat.', ['count' => $this->createdCount])
            : parent::getCreatedNotificationTitle();
    }

    protected function getRedirectUrl(): string
    {
        return $this->createdCount > 1
            ? static::getResource()::getUrl('index')
            : parent::getRedirectUrl();
    }
}
