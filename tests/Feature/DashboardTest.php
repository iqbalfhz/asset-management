<?php

use App\Filament\Pages\ActivityLog;
use App\Filament\Widgets\AssetOverview;
use App\Filament\Widgets\DepreciationByClass;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Company;
use App\Models\Depreciation;
use App\Models\Role;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');
    $this->travelTo(now()->setDate(2026, 11, 11));

    $this->tcm = Company::factory()->create();
    $this->itCategory = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $this->gaCategory = Category::factory()->create(['role_id' => Role::findByName('admin_ga')->id]);
    $this->fourYears = Depreciation::factory()->create(['nama' => 'Elektronik 4 tahun', 'jumlah_bulan' => 48]);
});

function assetIn(Company $company, Category $category, array $attributes = []): Asset
{
    return Asset::factory()->create($attributes + [
        'company_id' => $company->id,
        'category_id' => $category->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
        'is_byod' => false,
    ]);
}

/**
 * @return array<string, mixed>
 */
function statValues(AssetOverview $widget): array
{
    return collect((fn (): array => $this->getStats())->call($widget))
        ->mapWithKeys(fn (Stat $stat): array => [(string) $stat->getLabel() => $stat->getValue()])
        ->all();
}

test('the dashboard counts only the assets the admin manages', function () {
    assetIn($this->tcm, $this->itCategory);
    assetIn($this->tcm, $this->itCategory, ['garansi_sampai' => '2026-12-01']);
    assetIn($this->tcm, $this->gaCategory);
    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));

    $stats = statValues(new AssetOverview);

    expect($stats['Tersedia'])->toBe(1)
        ->and($stats['Garansi habis'])->toBe(0);
});

test('depreciation is summed per class, leaving out BYOD devices', function () {
    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->tcm->id]));
    $bought = ['depreciation_id' => $this->fourYears->id, 'harga_beli' => 12_000_000, 'tgl_beli' => '2026-01-15'];
    assetIn($this->tcm, $this->itCategory, $bought);
    assetIn($this->tcm, $this->itCategory, $bought);
    assetIn($this->tcm, $this->itCategory, $bought + ['is_byod' => true]);

    expect((new DepreciationByClass)->rows()->all())->toBe([
        'Elektronik 4 tahun' => ['jumlah' => 2, 'perolehan' => 24_000_000, 'buku' => 19_500_000],
    ]);
});

test('only a super admin opens the activity log', function () {
    $this->actingAs(userWithRole('admin_it'))->get(ActivityLog::getUrl())->assertForbidden();
    $this->actingAs(userWithRole('super_admin'))->get(ActivityLog::getUrl())->assertOk();
});

test('the activity log can be narrowed to one asset', function () {
    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->tcm->id]));
    $laptop = assetIn($this->tcm, $this->itCategory, ['nama' => 'Laptop Lama']);
    $desk = assetIn($this->tcm, $this->gaCategory);
    $laptop->update(['nama' => 'Laptop Baru']);

    livewire(ActivityLog::class)
        ->filterTable('aset', ['asset_id' => $laptop->id])
        ->assertCanSeeTableRecords(Activity::query()->where('subject_id', $laptop->id)->where('subject_type', $laptop->getMorphClass())->get())
        ->assertCanNotSeeTableRecords(Activity::query()->where('subject_id', $desk->id)->where('subject_type', $desk->getMorphClass())->get())
        ->assertSee('nama: Laptop Lama → Laptop Baru');
});
