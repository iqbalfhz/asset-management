<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use BezhanSalleh\FilamentShield\Support\Utils;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

// Form role berisi data role dan checklist izin: izin dipisah sebelum disimpan, lalu disinkronkan ke role.
trait SyncsPermissions
{
    /** @var Collection<int, string> */
    public Collection $permissions;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function separatePermissions(array $data): array
    {
        // 'panel' adalah kolom role tambahan project, bukan izin.
        $roleFields = ['name', 'guard_name', 'panel'];

        $this->permissions = collect(Arr::except($data, [...$roleFields, 'select_all']))->flatten()->unique()->values();

        return Arr::only($data, $roleFields);
    }

    protected function syncPermissions(): void
    {
        $this->record->syncPermissions($this->permissions->map(fn (string $permission) => Utils::getPermissionModel()::firstOrCreate([
            'name' => $permission,
            'guard_name' => $this->data['guard_name'],
        ])));
    }
}
