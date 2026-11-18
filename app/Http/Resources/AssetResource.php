<?php

namespace App\Http\Resources;

use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Room;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Asset
 */
class AssetResource extends JsonResource
{
    /**
     * Relasi yang dibaca resource ini; pemegang dibaca tanpa scope, seperti di BAST.
     *
     * @return array<int|string, mixed>
     */
    public static function relations(): array
    {
        return [
            'company', 'category', 'status', 'assetModel.manufacturer', 'room.floor.building',
            'assigned' => fn (MorphTo $morph) => $morph->withoutGlobalScopes()->morphWith([Room::class => ['floor.building']]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode' => $this->kode,
            'nama' => $this->nama,
            'perusahaan' => $this->company->kode,
            'kategori' => $this->category->nama,
            'model' => $this->assetModel?->fullName(),
            'no_seri' => $this->no_seri,
            'status' => $this->status->nama,
            'ruang' => $this->room?->path(),
            'pemegang' => $this->assigned === null ? null : [
                'jenis' => array_search($this->assigned_type, Assignment::TARGET_TYPES, true),
                'id' => $this->assigned_id,
                'nama' => Assignment::labelFor($this->assigned),
            ],
            'garansi_sampai' => $this->garansi_sampai?->toDateString(),
            'jadwal_audit' => $this->next_audit_date?->toDateString(),
            'spesifikasi' => $this->spesifikasi,
            'diperbarui' => $this->updated_at->toIso8601String(),
        ];
    }
}
