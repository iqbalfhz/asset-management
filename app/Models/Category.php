<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use App\Models\Scopes\ManagedCategoryScope;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['nama', 'kode', 'role_id', 'template_spesifikasi', 'eula', 'warna'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, LogsChanges;

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    // Kategori yang boleh dipakai user yang login untuk asetnya.
    #[Scope]
    protected function managedByCurrentUser(Builder $query): void
    {
        $roleIds = ManagedCategoryScope::restrictedRoleIds();

        if ($roleIds !== null) {
            $query->whereIn('role_id', $roleIds);
        }
    }

    // Kategori yang punya aset bertanda Boleh diminta di perusahaan itu, dari pengelola mana pun; aset Dihapuskan tidak dihitung.
    #[Scope]
    protected function requestableIn(Builder $query, int $companyId): void
    {
        $query->whereIn('id', Asset::withoutGlobalScopes()
            ->select('category_id')
            ->where('company_id', $companyId)
            ->where('requestable', true)
            ->whereRelation('status', 'nama', '!=', Status::DIHAPUSKAN));
    }

    // Kode dipakai di kode aset, jadi selalu disimpan huruf besar.
    protected function kode(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => strtoupper($value));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_spesifikasi' => 'array',
        ];
    }
}
