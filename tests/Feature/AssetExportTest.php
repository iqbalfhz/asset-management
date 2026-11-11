<?php

use App\Filament\Exports\AssetExporter;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Building;
use App\Models\Category;
use App\Models\Company;
use App\Models\Depreciation;
use App\Models\Employee;
use App\Models\Floor;
use App\Models\Role;
use App\Models\Room;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');
    $this->travelTo(now()->setDate(2026, 11, 11));

    $this->tcm = Company::factory()->create();
    $this->laptops = Category::factory()->create(['nama' => 'Laptop', 'role_id' => Role::findByName('admin_it')->id]);
    $this->fourYears = Depreciation::factory()->create(['nama' => 'Elektronik 4 tahun', 'jumlah_bulan' => 48]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->actingAs($this->adminIt);
});

function laptopBoughtAt(Company $company, Category $category, Depreciation $class, array $attributes = []): Asset
{
    return Asset::factory()->create($attributes + [
        'company_id' => $company->id,
        'category_id' => $category->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
        'depreciation_id' => $class->id,
        'harga_beli' => 12_000_000,
        'tgl_beli' => '2026-01-15',
        'is_byod' => false,
    ]);
}

test('book value falls in a straight line from the purchase date and stops at zero', function () {
    $laptop = laptopBoughtAt($this->tcm, $this->laptops, $this->fourYears);

    expect($laptop->bookValue())->toBe(9_750_000)
        ->and($laptop->bookValue(now()->setDate(2031, 1, 1)))->toBe(0)
        ->and(laptopBoughtAt($this->tcm, $this->laptops, $this->fourYears, ['is_byod' => true])->bookValue())->toBeNull()
        ->and(laptopBoughtAt($this->tcm, $this->laptops, $this->fourYears, ['harga_beli' => null])->bookValue())->toBeNull();
});

test('an export row carries the location, holder, depreciation class and book value', function () {
    $floor = Floor::factory()->create(['building_id' => Building::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Gedung A'])->id, 'nama' => 'Lantai 2']);
    $room = Room::factory()->create(['floor_id' => $floor->id, 'nama' => 'Keuangan']);
    $laptop = laptopBoughtAt($this->tcm, $this->laptops, $this->fourYears, ['room_id' => $room->id]);
    $holder = Employee::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Rina', 'nik' => '2026001']);
    Assignment::handOver(new Collection([$laptop]), $holder, ['tanggal' => now()->toDateString(), 'kondisi' => 'Baik']);

    $row = AssetExporter::test(columnMap: [
        'category.nama' => 'Kategori',
        'status.nama' => 'Status',
        'lokasi' => 'Lokasi',
        'pemegang' => 'Pemegang',
        'depreciation.nama' => 'Kelas penyusutan',
        'nilai_buku' => 'Nilai buku',
    ])->export($laptop->fresh());

    expect($row)->toBe(['Laptop', Status::DIPAKAI, 'Gedung A — Lantai 2 — Keuangan', 'Rina (2026001)', 'Elektronik 4 tahun', '9750000']);
});

test('a value that looks like a spreadsheet formula is exported as text', function () {
    $laptop = laptopBoughtAt($this->tcm, $this->laptops, $this->fourYears, ['nama' => '=HYPERLINK("http://contoh.test","klik")']);

    expect(AssetExporter::test(columnMap: ['nama' => 'Nama'])->export($laptop))
        ->toBe(['\'=HYPERLINK("http://contoh.test","klik")']);
});

test('an export follows the table filter, so it works as a report per category', function () {
    $laptop = laptopBoughtAt($this->tcm, $this->laptops, $this->fourYears);
    laptopBoughtAt($this->tcm, Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]), $this->fourYears);
    $exports = ExportAction::fake();

    livewire(ListAssets::class)
        ->filterTable('category_id', $this->laptops->id)
        ->callAction('export')
        ->assertHasNoFormErrors();

    $exports->assertDispatched(AssetExporter::class, function (Export $export, Builder $query) use ($laptop): bool {
        expect($query->pluck('assets.id')->all())->toBe([$laptop->id]);

        return true;
    });
});

test('an export file is refused to other users and to its owner before passing 2FA', function () {
    $export = Export::create([
        'file_name' => 'aset',
        'file_disk' => 'local',
        'exporter' => AssetExporter::class,
        'total_rows' => 1,
        'user_id' => $this->adminIt->id,
    ]);

    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]));
    $this->get(route('filament.exports.download', ['export' => $export, 'format' => 'csv']))->assertForbidden();

    // be() skips the 2FA session that actingAs() adds in TestCase.
    $this->be($this->adminIt);
    $this->get(route('filament.exports.download', ['export' => $export, 'format' => 'csv']))->assertForbidden();
});

test('an owner without 2FA passes the export download check', function () {
    $owner = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id], twoFactor: false);
    $export = Export::create([
        'file_name' => 'aset',
        'file_disk' => 'local',
        'exporter' => AssetExporter::class,
        'total_rows' => 1,
        'user_id' => $owner->id,
    ]);

    expect($owner->can('view', $export))->toBeTrue();
});
