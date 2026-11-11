<?php

use App\Enums\DisposalMethod;
use App\Enums\DisposalStatus;
use App\Filament\Resources\Disposals\Pages\CreateDisposal;
use App\Filament\Resources\Disposals\Pages\ListDisposals;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Company;
use App\Models\Disposal;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Testing\TestView;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');
    $this->travelTo(now()->setDate(2026, 11, 11));

    $this->tcm = Company::factory()->create(['nama' => 'Tangcity', 'kode' => 'TCM']);
    $this->laptopCategory = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id, 'name' => 'Iqbal']);
    $this->superAdmin = userWithRole('super_admin', attributes: ['company_id' => $this->tcm->id, 'name' => 'Direktur']);
    $this->broken = brokenLaptop($this->tcm, $this->laptopCategory);

    $this->actingAs($this->adminIt);
});

function brokenLaptop(Company $company, Category $category, string $status = Status::RUSAK): Asset
{
    $asset = Asset::factory()->create([
        'company_id' => $company->id,
        'category_id' => $category->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
        'nama' => 'Laptop Lama',
    ]);
    $asset->transitionTo(Status::named($status));

    return $asset;
}

test('an admin submits a disposal for a broken asset', function () {
    livewire(CreateDisposal::class)
        ->fillForm(['asset_id' => $this->broken->id, 'metode' => DisposalMethod::Dijual->value, 'nilai_jual' => 500000, 'alasan' => 'Rusak total'])
        ->call('create')
        ->assertHasNoFormErrors();

    $disposal = Disposal::query()->sole();

    expect($disposal->status)->toBe(DisposalStatus::Diajukan)
        ->and($disposal->nilai_jual)->toBe(500000)
        ->and($disposal->diajukan_oleh)->toBe($this->adminIt->id)
        ->and($this->broken->refresh()->status->nama)->toBe(Status::RUSAK);
});

test('only a broken or lost asset can be submitted, once at a time', function () {
    $working = brokenLaptop($this->tcm, $this->laptopCategory, Status::TERSEDIA);
    Disposal::submit($this->broken, ['alasan' => 'Rusak', 'metode' => DisposalMethod::Dibuang]);

    expect(fn () => Disposal::submit($working, ['alasan' => 'Tua', 'metode' => DisposalMethod::Dibuang]))->toThrow(LogicException::class)
        ->and(fn () => Disposal::submit($this->broken, ['alasan' => 'Rusak', 'metode' => DisposalMethod::Dibuang]))->toThrow(LogicException::class)
        ->and(Disposal::count())->toBe(1);
});

test('an admin cannot approve their own submission', function () {
    $disposal = Disposal::submit($this->broken, ['alasan' => 'Rusak', 'metode' => DisposalMethod::Dibuang]);

    livewire(ListDisposals::class)
        ->assertActionHidden(TestAction::make('approve')->table($disposal))
        ->assertActionHidden(TestAction::make('reject')->table($disposal));

    expect(fn () => $disposal->approve())->toThrow(LogicException::class)
        ->and($disposal->refresh()->status)->toBe(DisposalStatus::Diajukan);
});

test('approval writes the asset off, frees its holder and numbers the report per company and year', function () {
    $holder = Employee::factory()->create(['company_id' => $this->tcm->id]);
    $this->broken->transitionTo(Status::named(Status::HILANG), ['assigned_type' => $holder->getMorphClass(), 'assigned_id' => $holder->id]);
    $first = Disposal::submit($this->broken, ['alasan' => 'Hilang saat dinas', 'metode' => DisposalMethod::Hilang]);
    $second = Disposal::submit(brokenLaptop($this->tcm, $this->laptopCategory), ['alasan' => 'Rusak', 'metode' => DisposalMethod::Dibuang]);
    $this->actingAs($this->superAdmin);

    livewire(ListDisposals::class)
        ->callAction(TestAction::make('approve')->table($first), data: ['catatan' => 'Sudah dicek'])
        ->assertNotified('Penghapusan disetujui')
        ->callAction(TestAction::make('approve')->table($second))
        ->assertNotified('Penghapusan disetujui');

    $this->broken->refresh();

    expect($this->broken->status->nama)->toBe(Status::DIHAPUSKAN)
        ->and($this->broken->assigned_id)->toBeNull()
        ->and($first->refresh()->no_berita_acara)->toBe('BAP/TCM/2026/0001')
        ->and($first->disetujui_oleh)->toBe($this->superAdmin->id)
        ->and($second->refresh()->no_berita_acara)->toBe('BAP/TCM/2026/0002')
        ->and(Asset::find($this->broken->id))->not->toBeNull();
});

test('a rejection needs a reason and leaves the asset as it was', function () {
    $disposal = Disposal::submit($this->broken, ['alasan' => 'Rusak', 'metode' => DisposalMethod::Dibuang]);
    $this->actingAs($this->superAdmin);

    livewire(ListDisposals::class)
        ->callAction(TestAction::make('reject')->table($disposal), data: ['catatan' => ''])
        ->assertHasFormErrors(['catatan' => 'required']);

    livewire(ListDisposals::class)
        ->callAction(TestAction::make('reject')->table($disposal), data: ['catatan' => 'Masih bisa diperbaiki'])
        ->assertNotified('Penghapusan ditolak');

    expect($disposal->refresh()->status)->toBe(DisposalStatus::Ditolak)
        ->and($disposal->no_berita_acara)->toBeNull()
        ->and($this->broken->refresh()->status->nama)->toBe(Status::RUSAK);
});

test('the report downloads only once approved and only for those who may see the asset', function () {
    $disposal = Disposal::submit($this->broken, ['alasan' => 'Rusak', 'metode' => DisposalMethod::Dibuang]);

    $this->get(route('filament.admin.disposals.report', $disposal))->assertNotFound();

    $this->actingAs($this->superAdmin);
    $disposal->approve();
    $this->actingAs($this->adminIt);

    $this->get(route('filament.admin.disposals.report', $disposal))
        ->assertOk()
        ->assertDownload('BAP-TCM-2026-0001.pdf');

    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));
    $this->get(route('filament.admin.disposals.report', $disposal))->assertNotFound();
});

test('the report lists the asset, method, sale value and both signatories', function () {
    $disposal = Disposal::submit($this->broken, ['alasan' => 'Layar pecah', 'metode' => DisposalMethod::Dijual, 'nilai_jual' => 500000]);
    $this->actingAs($this->superAdmin);
    $disposal->approve();
    $disposal->load(['asset.company', 'asset.category', 'submitter', 'decider']);

    (new TestView(view('pdf.berita-acara-penghapusan', [
        'disposal' => $disposal,
        'company' => $this->tcm,
        'kop' => null,
        'logo' => null,
    ])))
        ->assertSee('BERITA ACARA PENGHAPUSAN ASET')
        ->assertSee('BAP/TCM/2026/0001')
        ->assertSee('Pada tanggal 11 November 2026')
        ->assertSee($this->broken->kode)
        ->assertSee('Dijual')
        ->assertSee('Rp 500.000')
        ->assertSeeInOrder(['Diajukan oleh', 'Disetujui oleh', 'Iqbal', 'Direktur']);
});
