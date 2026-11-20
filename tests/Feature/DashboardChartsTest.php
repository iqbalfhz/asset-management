<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\ActivityChart;
use App\Filament\Widgets\AssetOverview;
use App\Filament\Widgets\AssetsByCategoryChart;
use App\Filament\Widgets\DashboardChart;
use App\Filament\Widgets\RecentAssignments;
use App\Filament\Widgets\ReplacementChart;
use App\Filament\Widgets\ValueByCategoryChart;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Depreciation;
use App\Models\Employee;
use App\Models\Maintenance;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Collection;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');
    $this->travelTo('2026-11-20 10:00');

    $this->tcm = Company::factory()->create(['nama' => 'Tangcity']);
    $this->laptops = Category::factory()->create(['nama' => 'Laptop', 'role_id' => Role::findByName('admin_it')->id]);
    $this->printers = Category::factory()->create(['nama' => 'Printer', 'role_id' => Role::findByName('admin_it')->id]);
    $this->desks = Category::factory()->create(['nama' => 'Meja', 'role_id' => Role::findByName('admin_ga')->id]);
    $this->employee = Employee::factory()->create(['company_id' => $this->tcm->id]);

    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->tcm->id]));
});

function assetWithStatus(Company $company, Category $category, string $status, array $attributes = []): Asset
{
    $asset = availableAsset($company, $category, $attributes);

    if ($status !== Status::TERSEDIA) {
        $asset->transitionTo(Status::named($status));
    }

    return $asset;
}

/**
 * @param  array<string, mixed>  $pageFilters
 * @return array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}
 */
function chartData(DashboardChart $chart, array $pageFilters = [], ?string $filter = null): array
{
    $chart->pageFilters = $pageFilters;
    $chart->filter = $filter ?? $chart->filter;

    return (fn (): array => $this->getData())->call($chart);
}

/**
 * @param  array<string, mixed>  $pageFilters
 * @return array<string, mixed>
 */
function overviewValues(array $pageFilters = []): array
{
    $widget = new AssetOverview;
    $widget->pageFilters = $pageFilters;

    return collect((fn (): array => $this->getStats())->call($widget))
        ->mapWithKeys(fn (Stat $stat): array => [(string) $stat->getLabel() => $stat->getValue()])
        ->all();
}

test('the dashboard filters by category, and offers companies only when there is more than one', function () {
    livewire(Dashboard::class)
        ->assertSee('Semua kategori')
        ->assertDontSee('Semua perusahaan');

    Company::factory()->create();

    livewire(Dashboard::class)->assertSee('Semua perusahaan');
});

test('the category filter narrows the summary numbers, the charts, and the tables', function () {
    $laptop = availableAsset($this->tcm, $this->laptops);
    $printer = availableAsset($this->tcm, $this->printers);
    Assignment::handOver(new Collection([$laptop]), $this->employee, ['tanggal' => '2026-11-20', 'kondisi' => 'Baik']);
    Assignment::handOver(new Collection([$printer]), $this->employee, ['tanggal' => '2026-11-20', 'kondisi' => 'Baik']);
    availableAsset($this->tcm, $this->printers);
    $onlyLaptops = ['kategori' => (string) $this->laptops->id];

    expect(overviewValues($onlyLaptops))->toMatchArray(['Tersedia' => 0, 'Dipakai' => 1])
        ->and(overviewValues())->toMatchArray(['Tersedia' => 1, 'Dipakai' => 2])
        ->and(chartData(new AssetsByCategoryChart, $onlyLaptops)['labels'])->toBe(['Laptop']);

    livewire(RecentAssignments::class, ['pageFilters' => $onlyLaptops])
        ->assertCanSeeTableRecords($laptop->assignments)
        ->assertCanNotSeeTableRecords($printer->assignments);
});

test('a filter typed in by hand cannot reach another company or another admin\'s category', function () {
    Setting::put('company_separation', true);
    $other = Company::factory()->create();
    availableAsset($other, $this->laptops);
    availableAsset($this->tcm, $this->desks);

    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]));

    expect(overviewValues(['perusahaan' => (string) $other->id])['Tersedia'])->toBe(0)
        ->and(overviewValues(['kategori' => (string) $this->desks->id])['Tersedia'])->toBe(0)
        ->and(chartData(new AssetsByCategoryChart, ['perusahaan' => (string) $other->id])['labels'])->toBe([]);
});

test('assets per category are grouped into available, in use, and not usable, leaving out archived ones', function () {
    assetWithStatus($this->tcm, $this->laptops, Status::TERSEDIA);
    assetWithStatus($this->tcm, $this->laptops, Status::TERSEDIA);
    assetWithStatus($this->tcm, $this->laptops, Status::DIPAKAI);
    assetWithStatus($this->tcm, $this->laptops, Status::DALAM_PERBAIKAN);
    assetWithStatus($this->tcm, $this->laptops, Status::DISIAPKAN);
    assetWithStatus($this->tcm, $this->laptops, Status::HILANG);
    assetWithStatus($this->tcm, $this->printers, Status::RUSAK);

    $data = chartData(new AssetsByCategoryChart);

    expect($data['labels'])->toBe(['Laptop', 'Printer'])
        ->and(array_column($data['datasets'], 'data'))->toBe([[2, 0], [1, 0], [2, 1]])
        ->and(array_column($data['datasets'], 'backgroundColor'))->toBe([DashboardChart::GREEN, DashboardChart::BLUE, DashboardChart::BRICK]);
});

test('categories past the largest ten are added up as Lainnya', function () {
    foreach (range(1, 11) as $number) {
        $category = Category::factory()->create(['nama' => "Kategori {$number}", 'role_id' => Role::findByName('admin_it')->id]);
        foreach (range(1, $number) as $ignored) {
            availableAsset($this->tcm, $category);
        }
    }
    availableAsset($this->tcm, $this->laptops);

    $data = chartData(new AssetsByCategoryChart);

    expect($data['labels'])->toHaveCount(11)
        ->and($data['labels'][0])->toBe('Kategori 11')
        ->and(end($data['labels']))->toBe('Lainnya')
        ->and(end($data['datasets'][0]['data']))->toBe(2);
});

test('monthly activity counts hand-overs, returns, and damage reports, and an unknown period falls back to twelve months', function () {
    $laptop = availableAsset($this->tcm, $this->laptops);
    Assignment::handOver(new Collection([$laptop]), $this->employee, ['tanggal' => '2026-09-05', 'kondisi' => 'Baik']);
    Assignment::takeBack(new Collection([$laptop->fresh()]), ['tanggal' => '2026-11-10', 'kondisi' => 'Baik']);
    Maintenance::factory()->create(['asset_id' => $laptop->id, 'created_at' => '2026-10-15 09:00']);
    Maintenance::factory()->create(['asset_id' => availableAsset($this->tcm, $this->printers)->id, 'created_at' => '2026-10-16 09:00']);

    $data = chartData(new ActivityChart, filter: '6');

    expect($data['labels'])->toBe(['Jun 2026', 'Jul 2026', 'Agt 2026', 'Sep 2026', 'Okt 2026', 'Nov 2026'])
        ->and(array_column($data['datasets'], 'data'))->toBe([[0, 0, 0, 1, 0, 0], [0, 0, 0, 0, 0, 1], [0, 0, 0, 0, 2, 0]])
        ->and(chartData(new ActivityChart, ['kategori' => (string) $this->laptops->id], '6')['datasets'][2]['data'])->toBe([0, 0, 0, 0, 1, 0])
        ->and(chartData(new ActivityChart, filter: 'semua')['labels'])->toHaveCount(12);
});

test('replacements are counted per end-of-life year, overdue ones first in brick red, or summed by purchase price', function () {
    $eol = fn (string $date, int $price, array $extra = []) => availableAsset($this->tcm, $this->laptops, ['eol_date' => $date, 'harga_beli' => $price] + $extra);
    $eol('2026-05-01', 10_000_000);
    $eol('2026-12-01', 12_500_000);
    $eol('2028-03-01', 8_000_000);
    $eol('2031-01-01', 9_000_000);
    $eol('2027-06-01', 7_000_000, ['is_byod' => true]);
    assetWithStatus($this->tcm, $this->laptops, Status::HILANG, ['eol_date' => '2027-06-01', 'harga_beli' => 5_000_000]);

    $count = chartData(new ReplacementChart);
    $value = chartData(new ReplacementChart, filter: 'nilai');

    expect($count['labels'])->toBe(['Sudah lewat', '2026', '2027', '2028', '2029', '2030'])
        ->and($count['datasets'][0]['data'])->toBe([1, 1, 0, 1, 0, 0])
        ->and($count['datasets'][0]['backgroundColor'][0])->toBe(DashboardChart::BRICK)
        ->and($value['datasets'][0]['data'])->toBe([10.0, 12.5, 0.0, 8.0, 0.0, 0.0]);
});

test('value per category sums purchase price and book value of depreciable assets only, in millions', function () {
    $bought = ['depreciation_id' => Depreciation::factory()->create(['jumlah_bulan' => 48])->id, 'harga_beli' => 12_000_000, 'tgl_beli' => '2026-01-15'];
    availableAsset($this->tcm, $this->laptops, $bought);
    availableAsset($this->tcm, $this->laptops, $bought);
    availableAsset($this->tcm, $this->laptops, $bought + ['is_byod' => true]);
    availableAsset($this->tcm, $this->laptops, ['harga_beli' => 5_000_000]);

    $data = chartData(new ValueByCategoryChart);

    // 10 dari 48 bulan berjalan: 2 × 12 juta × 38/48 = 19 juta.
    expect($data['labels'])->toBe(['Laptop'])
        ->and(array_column($data['datasets'], 'data'))->toBe([[24.0], [19.0]]);
});

test('every chart is described as a table for screen readers and does not poll the database', function (string $chart) {
    availableAsset($this->tcm, $this->laptops);

    $widget = new $chart;
    $widget->pageFilters = [];

    expect($widget->getChartAssistiveContent()->render())->toContain($widget->getHeading())
        ->and((fn (): ?string => $this->getPollingInterval())->call($widget))->toBeNull()
        ->and($widget)->toBeInstanceOf(ChartWidget::class);

    livewire($chart, ['pageFilters' => ['kategori' => (string) $this->laptops->id]])->assertSee($widget->getHeading());
})->with([AssetsByCategoryChart::class, ActivityChart::class, ReplacementChart::class, ValueByCategoryChart::class]);

test('charts are only for users who may see assets', function () {
    expect(AssetsByCategoryChart::canView())->toBeTrue();

    $this->actingAs(userWithRole('tamu'));

    expect(AssetsByCategoryChart::canView())->toBeFalse();
});
