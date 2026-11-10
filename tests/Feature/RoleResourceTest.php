<?php

use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(userWithRole('super_admin'));
});

test('super admin can create a role for the karyawan panel', function () {
    livewire(CreateRole::class)
        ->fillForm(['name' => 'gudang', 'panel' => 'karyawan'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Role::findByName('gudang')->panel)->toBe('karyawan');
});

test('super admin can move a role to another panel', function () {
    $role = Role::create(['name' => 'gudang', 'panel' => 'admin']);

    livewire(EditRole::class, ['record' => $role->getRouteKey()])
        ->fillForm(['panel' => 'karyawan'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($role->refresh()->panel)->toBe('karyawan');
});

test('a role only accepts a known panel', function () {
    livewire(CreateRole::class)
        ->fillForm(['name' => 'gudang', 'panel' => 'gudang'])
        ->call('create')
        ->assertHasFormErrors(['panel' => 'in']);
});
