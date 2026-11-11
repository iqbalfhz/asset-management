<?php

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Building;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Floor;
use App\Models\Role;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Database\Eloquent\Collection;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    $this->travelTo(now()->setDate(2026, 11, 11));

    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $this->laptop = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->actingAs($this->adminIt);

    $this->employee = Employee::factory()->create(['company_id' => $this->tcm->id]);
    $this->details = ['tanggal' => '2026-11-11', 'kondisi' => 'Baik'];
});

function availableAsset(Company $company, Category $category, array $attributes = []): Asset
{
    return Asset::factory()->create($attributes + [
        'company_id' => $company->id,
        'category_id' => $category->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
    ]);
}

function roomOf(Company $company): Room
{
    $floor = Floor::factory()->create(['building_id' => Building::factory()->create(['company_id' => $company->id])->id]);

    return Room::factory()->create(['floor_id' => $floor->id]);
}

test('handing over to an employee puts the asset in use with that holder, waiting for acceptance', function () {
    $asset = availableAsset($this->tcm, $this->laptop);

    $noBast = Assignment::handOver(new Collection([$asset]), $this->employee, $this->details + ['expected_return_at' => '2026-12-01']);

    $asset->refresh();
    $assignment = $asset->assignments()->sole();

    expect($noBast)->toBe('BAST/TCM/2026/0001')
        ->and($asset->status->nama)->toBe(Status::DIPAKAI)
        ->and($asset->assigned->is($this->employee))->toBeTrue()
        ->and($assignment->jenis)->toBe(AssignmentType::Keluar)
        ->and($assignment->status)->toBe(AssignmentStatus::Menunggu)
        ->and($assignment->accepted_at)->toBeNull()
        ->and($assignment->expected_return_at->toDateString())->toBe('2026-12-01')
        ->and($assignment->created_by)->toBe($this->adminIt->id);
});

test('handing over to a room moves the asset there and counts as accepted at once', function () {
    $asset = availableAsset($this->tcm, $this->laptop);
    $room = roomOf($this->tcm);

    Assignment::handOver(new Collection([$asset]), $room, $this->details);

    $assignment = $asset->assignments()->sole();

    expect($asset->refresh()->room_id)->toBe($room->id)
        ->and($assignment->status)->toBe(AssignmentStatus::Diterima)
        ->and($assignment->accepted_at)->not->toBeNull();
});

test('assets handed over together share one BAST number that runs on per company and year', function () {
    $assets = new Collection([availableAsset($this->tcm, $this->laptop), availableAsset($this->tcm, $this->laptop), availableAsset($this->tcm, $this->laptop)]);

    $first = Assignment::handOver($assets, $this->employee, $this->details);
    $second = Assignment::handOver(new Collection([availableAsset($this->tcm, $this->laptop)]), $this->employee, $this->details);
    $this->travelTo(now()->setDate(2027, 1, 4));
    $nextYear = Assignment::handOver(new Collection([availableAsset($this->tcm, $this->laptop)]), $this->employee, $this->details);

    expect(Assignment::where('no_bast', $first)->count())->toBe(3)
        ->and($first)->toBe('BAST/TCM/2026/0001')
        ->and($second)->toBe('BAST/TCM/2026/0002')
        ->and($nextYear)->toBe('BAST/TCM/2027/0001');
});

test('the BAST number follows the format set for the company', function () {
    Setting::put('format_nomor_bast', 'ST-{KODE}-{BULAN}{TAHUN}-{NOMOR}', $this->tcm->id);

    $noBast = Assignment::handOver(new Collection([availableAsset($this->tcm, $this->laptop)]), $this->employee, $this->details);

    expect($noBast)->toBe('ST-TCM-112026-0001');
});

test('an asset that is not Tersedia stops the whole hand over and nothing changes', function () {
    $available = availableAsset($this->tcm, $this->laptop);
    $preparing = availableAsset($this->tcm, $this->laptop, ['status_id' => Status::named(Status::DISIAPKAN)->id]);

    expect(fn () => Assignment::handOver(new Collection([$available, $preparing]), $this->employee, $this->details))
        ->toThrow(LogicException::class, "Aset {$preparing->kode} belum Tersedia, jadi belum bisa diserahkan.");

    expect($available->refresh()->status->nama)->toBe(Status::TERSEDIA)
        ->and($available->assigned_id)->toBeNull()
        ->and(Assignment::count())->toBe(0);
});

test('an asset cannot be handed over to itself or to an asset attached to it', function () {
    $pc = availableAsset($this->tcm, $this->laptop);
    $monitor = availableAsset($this->tcm, $this->laptop);
    Assignment::handOver(new Collection([$monitor]), $pc, $this->details);

    expect(fn () => Assignment::handOver(new Collection([$pc]), $pc, $this->details))->toThrow(LogicException::class)
        ->and(fn () => Assignment::handOver(new Collection([$pc]), $monitor->refresh(), $this->details))->toThrow(LogicException::class)
        ->and($pc->refresh()->status->nama)->toBe(Status::TERSEDIA);
});

test('an inactive employee or one from another company cannot receive assets', function () {
    $asset = availableAsset($this->tcm, $this->laptop);
    $resigned = Employee::factory()->create(['company_id' => $this->tcm->id, 'is_active' => false]);
    $otherCompany = Employee::factory()->create(['company_id' => Company::factory()->create()->id]);

    expect(fn () => Assignment::handOver(new Collection([$asset]), $resigned, $this->details))->toThrow(LogicException::class)
        ->and(fn () => Assignment::handOver(new Collection([$asset]), $otherCompany, $this->details))->toThrow(LogicException::class)
        ->and(Assignment::count())->toBe(0);
});

test('returning an asset frees it, sends it to its default room and records the return', function () {
    $store = roomOf($this->tcm);
    $asset = availableAsset($this->tcm, $this->laptop, ['default_room_id' => $store->id]);
    Assignment::handOver(new Collection([$asset]), roomOf($this->tcm), $this->details);

    $noBast = Assignment::takeBack(new Collection([$asset->refresh()]), ['tanggal' => '2026-11-11', 'kondisi' => 'Lecet ringan']);

    $asset->refresh();
    $return = $asset->assignments()->where('jenis', AssignmentType::Kembali)->sole();

    expect($noBast)->toBe('BAST/TCM/2026/0002')
        ->and($asset->status->nama)->toBe(Status::TERSEDIA)
        ->and($asset->assigned_type)->toBeNull()
        ->and($asset->room_id)->toBe($store->id)
        ->and($return->kondisi)->toBe('Lecet ringan')
        ->and($return->target_id)->not->toBeNull();
});

test('an attached asset of any category returns with its parent and cannot return alone while the parent is in use', function () {
    $pc = availableAsset($this->tcm, $this->laptop);
    $furniture = Category::factory()->create(['role_id' => Role::findByName('admin_ga')->id]);
    $monitor = availableAsset($this->tcm, $furniture);
    $monitor->transitionTo(Status::named(Status::DIPAKAI), ['assigned_type' => $pc->getMorphClass(), 'assigned_id' => $pc->id]);
    Assignment::handOver(new Collection([$pc]), $this->employee, $this->details);

    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->tcm->id]));
    expect(fn () => Assignment::takeBack(new Collection([$monitor]), $this->details))->toThrow(LogicException::class);

    $this->actingAs($this->adminIt);
    $noBast = Assignment::takeBack(new Collection([$pc->refresh()]), $this->details);

    expect($monitor->refresh()->status->nama)->toBe(Status::TERSEDIA)
        ->and($monitor->assigned_id)->toBeNull()
        ->and(Assignment::withoutGlobalScopes()->where('no_bast', $noBast)->pluck('asset_id')->sort()->values()->all())->toBe([$pc->id, $monitor->id]);
});

test('an asset that is not in use cannot be returned', function () {
    $asset = availableAsset($this->tcm, $this->laptop);

    expect(fn () => Assignment::takeBack(new Collection([$asset]), $this->details))
        ->toThrow(LogicException::class, "Aset {$asset->kode} tidak sedang dipakai.");
});
