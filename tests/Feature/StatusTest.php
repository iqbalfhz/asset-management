<?php

use App\Enums\StatusType;
use App\Filament\Resources\Statuses\Pages\ManageStatuses;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');
    $this->actingAs(userWithRole('admin_it'));
});

test('the seeder creates the seven core statuses', function () {
    expect(Status::where('is_core', true)->pluck('tipe', 'nama')->all())->toEqual([
        'Disiapkan' => StatusType::Disiapkan,
        'Tersedia' => StatusType::SiapDipakai,
        'Dipakai' => StatusType::SiapDipakai,
        'Dalam Perbaikan' => StatusType::TidakBisaDipakai,
        'Rusak' => StatusType::TidakBisaDipakai,
        'Hilang' => StatusType::Arsip,
        'Dihapuskan' => StatusType::Arsip,
    ]);
});

test('a core status has no delete button and cannot be deleted in code', function () {
    $tersedia = Status::firstWhere('nama', 'Tersedia');

    livewire(ManageStatuses::class)
        ->assertActionHidden(TestAction::make('delete')->table($tersedia));

    expect(fn () => $tersedia->delete())->toThrow(LogicException::class)
        ->and(Status::find($tersedia->id))->not->toBeNull();
});

test('a core status keeps its name and type but its color can change', function () {
    $tersedia = Status::firstWhere('nama', 'Tersedia');

    expect(fn () => $tersedia->update(['nama' => 'Siap']))->toThrow(LogicException::class);

    $tersedia->refresh()->update(['warna' => '#000000']);

    expect($tersedia->refresh()->warna)->toBe('#000000');
});

test('admin IT can create and delete a custom status', function () {
    livewire(ManageStatuses::class)
        ->callAction(CreateAction::class, data: ['nama' => 'Dipinjamkan', 'tipe' => StatusType::SiapDipakai->value, 'warna' => '#336699'])
        ->assertHasNoActionErrors();

    $dipinjamkan = Status::firstWhere('nama', 'Dipinjamkan');
    expect($dipinjamkan->is_core)->toBeFalse();

    livewire(ManageStatuses::class)->callAction(TestAction::make('delete')->table($dipinjamkan));

    expect(Status::find($dipinjamkan->id))->toBeNull();
});
