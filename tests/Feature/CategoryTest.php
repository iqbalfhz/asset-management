<?php

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->actingAs(userWithRole('admin_it'));
});

test('admin IT can create a category and the code is stored in capitals', function () {
    livewire(ManageCategories::class)
        ->callAction(CreateAction::class, data: [
            'nama' => 'Laptop',
            'kode' => 'lpt',
            'role_id' => Role::findByName('admin_it')->id,
            'warna' => '#2f6b55',
            'template_spesifikasi' => ['Prosesor', 'RAM'],
            'eula' => 'Laptop dipakai hanya untuk pekerjaan kantor.',
        ])
        ->assertHasNoActionErrors();

    $laptop = Category::firstWhere('nama', 'Laptop');

    expect($laptop->kode)->toBe('LPT')
        ->and($laptop->template_spesifikasi)->toBe(['Prosesor', 'RAM'])
        ->and($laptop->role->name)->toBe('admin_it');
});

test('a duplicate category code is rejected', function () {
    Category::factory()->create(['kode' => 'LPT']);

    livewire(ManageCategories::class)
        ->callAction(CreateAction::class, data: [
            'nama' => 'Laptop Baru',
            'kode' => 'LPT',
            'role_id' => Role::findByName('admin_it')->id,
            'warna' => '#2f6b55',
        ])
        ->assertHasActionErrors(['kode' => 'unique']);
});

test('the manager role must be an admin panel role', function () {
    livewire(ManageCategories::class)
        ->callAction(CreateAction::class, data: [
            'nama' => 'Laptop',
            'kode' => 'LPT',
            'role_id' => Role::findByName('karyawan')->id,
            'warna' => '#2f6b55',
        ])
        ->assertHasActionErrors(['role_id']);

    expect(Category::count())->toBe(0);
});
