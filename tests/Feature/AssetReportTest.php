<?php

use App\Filament\Pages\AssetReport;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Depreciation;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestView;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    $this->travelTo('2026-11-19 10:00');
    $this->actingAs($this->superAdmin = userWithRole('super_admin'));
    Filament::setCurrentPanel('admin');

    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $laptops = Category::factory()->create(['nama' => 'Laptop', 'role_id' => Role::findByName('admin_it')->id]);
    $furniture = Category::factory()->create(['nama' => 'Furnitur', 'role_id' => Role::findByName('admin_ga')->id]);
    $this->finance = Department::factory()->create(['nama' => 'Keuangan']);
    $marketing = Department::factory()->create(['nama' => 'Pemasaran']);
    $sari = Employee::factory()->create(['company_id' => $this->tcm->id, 'department_id' => $this->finance->id]);

    // Rp 36 juta disusutkan 36 bulan: nilai bukunya Rp 24 juta setelah setahun.
    $bought = ['tgl_beli' => '2025-11-19', 'harga_beli' => 36_000_000, 'depreciation_id' => Depreciation::factory()->create(['jumlah_bulan' => 36])->id];
    $handOver = fn ($asset, $target) => Assignment::handOver(new Collection([$asset]), $target, ['tanggal' => '2026-11-19', 'kondisi' => 'Baik']);

    $handOver($sarisLaptop = availableAsset($this->tcm, $laptops, $bought), $sari);
    $handOver(availableAsset($this->tcm, $laptops, ['harga_beli' => 3_000_000]), $sarisLaptop);
    $handOver(availableAsset($this->tcm, $laptops, $bought), $marketing);
    $handOver(availableAsset($this->tcm, $furniture, ['harga_beli' => 2_000_000]), $sari);
    availableAsset($this->tcm, $laptops, $bought);

    availableAsset($this->tcm, $laptops, $bought)->transitionTo(Status::named(Status::DIHAPUSKAN));
    availableAsset($this->tcm, $laptops, $bought + ['is_byod' => true]);
    availableAsset(Company::factory()->create(), $laptops, $bought);
});

/**
 * Data yang dikirim ke template PDF saat laporan diunduh.
 *
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function assetReportData(array $query): array
{
    $data = [];
    View::composer('pdf.laporan-aset', function (Illuminate\View\View $view) use (&$data): void {
        $data = $view->getData();
    });

    test()->get(route('filament.admin.reports.assets', $query))->assertOk();

    return $data;
}

test('an admin downloads the asset report as a PDF from the Laporan menu', function () {
    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]));

    livewire(AssetReport::class)
        ->assertSchemaStateSet(['perusahaan' => $this->tcm->id, 'tanggal' => '2026-11-19'])
        ->fillForm(['departemen' => $this->finance->id])
        ->call('download')
        ->assertRedirect(route('filament.admin.reports.assets', ['perusahaan' => $this->tcm->id, 'departemen' => $this->finance->id, 'tanggal' => '2026-11-19']));

    $this->get(route('filament.admin.reports.assets', ['perusahaan' => $this->tcm->id, 'tanggal' => '2026-11-19']))
        ->assertOk()
        ->assertDownload('Laporan-aset-TCM-2026-11-19.pdf');
});

test('only users who may view assets open the report', function () {
    Role::findByName('admin_ga')->revokePermissionTo('ViewAny:Asset');
    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));

    $this->get(AssetReport::getUrl())->assertForbidden();
    $this->get(route('filament.admin.reports.assets', ['perusahaan' => $this->tcm->id, 'tanggal' => '2026-11-19']))->assertForbidden();
});

test('the report counts and values the company\'s assets per category, status and department', function () {
    $data = assetReportData(['perusahaan' => $this->tcm->id, 'tanggal' => '2026-11-19']);

    expect($data['total'])->toBe(['jumlah' => 5, 'harga' => 113_000_000, 'nilai' => 72_000_000])
        ->and($data['withoutValue'])->toBe(2)
        ->and($data['restricted'])->toBeFalse()
        ->and(collect($data['sections'])->pluck('groups', 'title')->all())->toBe([
            'Per kategori' => [
                'Furnitur' => ['jumlah' => 1, 'harga' => 2_000_000, 'nilai' => null],
                'Laptop' => ['jumlah' => 4, 'harga' => 111_000_000, 'nilai' => 72_000_000],
            ],
            'Per status' => [
                'Dipakai' => ['jumlah' => 4, 'harga' => 77_000_000, 'nilai' => 48_000_000],
                'Tersedia' => ['jumlah' => 1, 'harga' => 36_000_000, 'nilai' => 24_000_000],
            ],
            'Per departemen' => [
                '' => ['jumlah' => 1, 'harga' => 36_000_000, 'nilai' => 24_000_000],
                'Keuangan' => ['jumlah' => 3, 'harga' => 41_000_000, 'nilai' => 24_000_000],
                'Pemasaran' => ['jumlah' => 1, 'harga' => 36_000_000, 'nilai' => 24_000_000],
            ],
        ]);

    (new TestView(view('pdf.laporan-aset', $data)))->assertSeeInOrder([
        'LAPORAN ASET', 'nilai buku per 19 November 2026', "Rp\u{a0}113.000.000", "Rp\u{a0}72.000.000",
        'Per departemen', 'Tidak dipegang karyawan atau departemen', 'Keuangan',
        '2 aset belum punya harga beli, tanggal beli, atau kelas penyusutan',
    ]);
});

test('an admin\'s report only holds the categories they manage, and a department report only that department', function () {
    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]));

    $data = assetReportData(['perusahaan' => $this->tcm->id, 'departemen' => $this->finance->id, 'tanggal' => '2027-11-19']);

    expect($data['total'])->toBe(['jumlah' => 2, 'harga' => 39_000_000, 'nilai' => 12_000_000])
        ->and($data['restricted'])->toBeTrue()
        ->and(collect($data['sections'])->pluck('title')->all())->toBe(['Per kategori', 'Per status']);
});

test('with company separation on, an admin cannot pick or download another company\'s report', function () {
    Setting::put('company_separation', true);
    $other = Company::factory()->create();
    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]));

    livewire(AssetReport::class)->assertFormFieldExists('perusahaan', fn (Select $field): bool => array_keys($field->getOptions()) === [$this->tcm->id]);

    $this->get(route('filament.admin.reports.assets', ['perusahaan' => $other->id, 'tanggal' => '2026-11-19']))->assertNotFound();
});
