<?php

use App\Filament\Imports\LocationImporter;
use App\Models\Building;
use App\Models\Company;
use App\Models\Floor;
use App\Models\Room;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));
});

test('a new room creates its building and floor in the company of the importing account', function () {
    $room = LocationImporter::test()->import(['gedung' => 'Gedung A', 'lantai' => 'Lantai 2', 'nama' => 'Ruang Keuangan'])
        ->assertImported()
        ->getRecord();

    expect($room->nama)->toBe('Ruang Keuangan')
        ->and($room->floor->nama)->toBe('Lantai 2')
        ->and($room->floor->building->nama)->toBe('Gedung A')
        ->and($room->floor->building->company_id)->toBe($this->tcm->id);
});

test('rooms on an existing building and floor reuse them', function () {
    $building = Building::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Gedung A']);
    $floor = Floor::factory()->create(['building_id' => $building->id, 'nama' => 'Lantai 2']);

    LocationImporter::test()->import(['gedung' => 'Gedung A', 'lantai' => 'Lantai 2', 'nama' => 'Ruang Rapat'])->assertImported();
    LocationImporter::test()->import(['gedung' => 'Gedung A', 'lantai' => 'Lantai 3', 'nama' => 'Gudang'])->assertImported();

    expect(Building::count())->toBe(1)
        ->and($floor->rooms()->pluck('nama')->all())->toBe(['Ruang Rapat'])
        ->and($building->floors()->pluck('nama')->sort()->values()->all())->toBe(['Lantai 2', 'Lantai 3']);
});

test('a room that already exists fails the row', function () {
    $building = Building::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Gedung A']);
    $floor = Floor::factory()->create(['building_id' => $building->id, 'nama' => 'Lantai 2']);
    Room::factory()->create(['floor_id' => $floor->id, 'nama' => 'Ruang Keuangan']);

    LocationImporter::test()->import(['gedung' => 'Gedung A', 'lantai' => 'Lantai 2', 'nama' => 'Ruang Keuangan'])
        ->assertHasRowFailure('Ruang Gedung A — Lantai 2 — Ruang Keuangan sudah ada.');

    expect(Room::count())->toBe(1);
});

test('a building of another company is not reused', function () {
    $other = Building::factory()->create(['company_id' => Company::factory()->create()->id, 'nama' => 'Gedung A']);

    $room = LocationImporter::test()->import(['gedung' => 'Gedung A', 'lantai' => 'Lantai 1', 'nama' => 'Lobi'])
        ->assertImported()
        ->getRecord();

    expect($room->floor->building->isNot($other))->toBeTrue()
        ->and($room->floor->building->company_id)->toBe($this->tcm->id)
        ->and($other->floors()->count())->toBe(0);
});

test('building, floor, and room are all required', function () {
    LocationImporter::test()->import(['gedung' => 'Gedung A', 'lantai' => '', 'nama' => 'Lobi'])
        ->assertHasErrors(['lantai']);

    expect(Building::count())->toBe(0);
});
