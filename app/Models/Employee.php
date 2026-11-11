<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use App\Models\Concerns\ReceivesAssets;
use App\Models\Scopes\CompanyScope;
use App\Models\Scopes\ManagedCategoryScope;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use LogicException;

#[Fillable(['company_id', 'nik', 'nama', 'jabatan', 'department_id', 'user_id', 'is_active'])]
#[ScopedBy([CompanyScope::class])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, LogsChanges, ReceivesAssets;

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Nonaktifkan karyawan yang resign. Ditolak selama masih memegang aset, kecuali dipaksa dengan alasan.
     */
    public function deactivate(?string $forcedReason = null): void
    {
        DB::transaction(function () use ($forcedReason): void {
            $held = $this->heldAssets()->lockForUpdate()->get();

            if ($held->isNotEmpty()) {
                if (blank($forcedReason)) {
                    throw new LogicException(__(':nama masih memegang :count aset. Proses pengembaliannya dulu.', ['nama' => $this->nama, 'count' => $held->count()]));
                }

                foreach ($held as $asset) {
                    $asset->transitionTo(Status::named(Status::HILANG));
                }

                activity()
                    ->performedOn($this)
                    ->event('dinonaktifkan_paksa')
                    ->withProperties(['alasan' => $forcedReason, 'aset' => $held->pluck('kode')->all()])
                    ->log(__('Karyawan dinonaktifkan paksa'));
            }

            $this->update(['is_active' => false]);
            $this->user?->update(['is_active' => false]);
        });
    }

    /**
     * Aset yang masih dipegang, dari kategori mana pun, supaya offboarding tidak melewatkan barang.
     *
     * @return MorphMany<Asset, $this>
     */
    public function heldAssets(): MorphMany
    {
        return $this->morphMany(Asset::class, 'assigned')
            ->withoutGlobalScope(ManagedCategoryScope::class)
            ->whereRelation('status', 'nama', Status::DIPAKAI);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Akun login karyawan; boleh kosong karena tidak semua karyawan punya akun.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
