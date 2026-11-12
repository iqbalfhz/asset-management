<?php

use App\Enums\AuditResult;
use App\Enums\AuditStatus;
use App\Filament\Exports\AuditItemExporter;
use App\Filament\Pages\Settings;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Filament\Resources\Assets\RelationManagers\AuditItemsRelationManager;
use App\Filament\Resources\Audits\AuditResource;
use App\Filament\Resources\Audits\Pages\CreateAudit;
use App\Filament\Resources\Audits\Pages\ListAudits;
use App\Filament\Resources\Audits\Pages\ScanAudit;
use App\Filament\Resources\Audits\Pages\ViewAudit;
use App\Filament\Resources\Audits\RelationManagers\ItemsRelationManager;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Audit;
use App\Models\Building;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Floor;
use App\Models\Role;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);

    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $this->itCategory = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $this->gaCategory = Category::factory()->create(['role_id' => Role::findByName('admin_ga')->id]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $floor = Floor::factory()->create(['building_id' => Building::factory()->create(['company_id' => $this->tcm->id])->id]);
    $this->lab = Room::factory()->create(['nama' => 'Lab', 'floor_id' => $floor->id]);
    $this->warehouse = Room::factory()->create(['nama' => 'Gudang', 'floor_id' => $floor->id]);

    $asset = fn (Category $category, Room $room): Asset => Asset::factory()->create([
        'company_id' => $this->tcm->id,
        'category_id' => $category->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
        'room_id' => $room->id,
    ]);

    $this->laptop = $asset($this->itCategory, $this->lab);
    $this->printer = $asset($this->itCategory, $this->lab);
    $this->lost = $asset($this->itCategory, $this->lab);
    $this->lost->transitionTo(Status::named(Status::HILANG));
    $this->chair = $asset($this->gaCategory, $this->lab);
    $this->projector = $asset($this->itCategory, $this->warehouse);

    $this->actingAs($this->adminIt);
});

test('a session lists the assets recorded in the room that the admin manages, without lost or disposed ones', function () {
    $audit = Audit::start($this->lab);

    expect($audit->status)->toBe(AuditStatus::Berjalan)
        ->and($audit->role_id)->toBe(Role::findByName('admin_it')->id)
        ->and($audit->items()->pluck('asset_id')->sort()->values()->all())->toBe(collect([$this->laptop->id, $this->printer->id])->sort()->values()->all())
        ->and($audit->resultCounts()[AuditResult::Menunggu->value])->toBe(2);
});

test('the same room cannot have two running sessions of the same role', function () {
    Audit::start($this->lab);

    expect(fn () => Audit::start($this->lab))->toThrow(LogicException::class, 'sedang diaudit');
});

test('scanning marks an asset of the room as found and an asset from another room as misplaced', function () {
    $audit = Audit::start($this->lab);

    $found = $audit->scan($this->laptop->kode);
    $misplaced = $audit->scan(url("/a/{$this->projector->kode}"));

    expect($found->hasil)->toBe(AuditResult::Ditemukan)
        ->and($found->discan_oleh)->toBe($this->adminIt->id)
        ->and($misplaced->hasil)->toBe(AuditResult::SalahTempat)
        ->and($misplaced->ruang_tercatat_id)->toBe($this->warehouse->id);

    $this->travel(5)->minutes();
    $again = $audit->scan(" {$this->laptop->kode} ");

    expect($again->is($found))->toBeTrue()
        ->and($again->discan_pada->equalTo($found->discan_pada))->toBeTrue();
});

test('scanning refuses unknown codes, assets the admin does not manage, and closed sessions', function () {
    $audit = Audit::start($this->lab);

    expect(fn () => $audit->scan('TCM-XXX-2026-99999'))->toThrow(LogicException::class, 'tidak dikenal')
        ->and(fn () => $audit->scan($this->chair->kode))->toThrow(LogicException::class, 'tidak dikenal');

    $audit->close();

    expect(fn () => $audit->scan($this->laptop->kode))->toThrow(LogicException::class, 'sudah ditutup');
});

test('closing marks unscanned assets as not found and schedules the next audit of found assets', function () {
    Setting::put('audit_interval_bulan', 6, $this->tcm->id);
    $audit = Audit::start($this->lab);
    $audit->scan($this->laptop->kode);
    $audit->scan($this->projector->kode);

    $audit->close();

    $nextAudit = now()->addMonthsNoOverflow(6)->toDateString();

    expect($audit->refresh()->status)->toBe(AuditStatus::Selesai)
        ->and($audit->ditutup_oleh)->toBe($this->adminIt->id)
        ->and($audit->items()->where('asset_id', $this->printer->id)->value('hasil'))->toBe(AuditResult::TidakDitemukan)
        ->and($this->laptop->refresh()->next_audit_date->toDateString())->toBe($nextAudit)
        ->and($this->projector->refresh()->next_audit_date->toDateString())->toBe($nextAudit)
        ->and($this->printer->refresh()->next_audit_date)->toBeNull()
        ->and(fn () => $audit->close())->toThrow(LogicException::class, 'sudah ditutup');
});

test('a misplaced asset can be moved to the audit room once', function () {
    $audit = Audit::start($this->lab);
    $item = $audit->scan($this->projector->kode);

    $item->moveToAuditRoom();

    expect($this->projector->refresh()->room_id)->toBe($this->lab->id)
        ->and($item->dipindahkan_pada)->not->toBeNull()
        ->and(fn () => $item->moveToAuditRoom())->toThrow(LogicException::class, 'belum dipindahkan');
});

test('an asset handed over to a room is not moved by the audit', function () {
    Assignment::handOver(new Collection([$this->projector]), $this->warehouse, ['tanggal' => now()->toDateString(), 'kondisi' => 'Baik']);
    $item = Audit::start($this->lab)->scan($this->projector->kode);

    expect(fn () => $item->moveToAuditRoom())->toThrow(LogicException::class, 'serah-terima')
        ->and($this->projector->refresh()->room_id)->toBe($this->warehouse->id);
});

test('an asset with an employee or department, directly or through its parent, is not expected in the room but is recorded when scanned', function () {
    $newInLab = fn (): Asset => Asset::factory()->create([
        'company_id' => $this->tcm->id,
        'category_id' => $this->itCategory->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
        'room_id' => $this->lab->id,
    ]);
    $monitor = $newInLab();
    $scanner = $newInLab();
    $handOver = fn (Asset $asset, Model $target) => Assignment::handOver(new Collection([$asset]), $target, ['tanggal' => now()->toDateString(), 'kondisi' => 'Baik']);
    $handOver($this->laptop, Employee::factory()->create(['company_id' => $this->tcm->id]));
    $handOver($monitor, $this->laptop->refresh());
    $handOver($scanner, Department::factory()->create(['company_id' => $this->tcm->id]));

    $audit = Audit::start($this->lab);

    expect($audit->items()->pluck('asset_id')->all())->toBe([$this->printer->id])
        ->and($audit->scan($this->laptop->kode)->hasil)->toBe(AuditResult::Ditemukan);
});

test('scanning a lost asset records it and points the admin to recovering it', function () {
    Filament::setCurrentPanel('admin');
    $audit = Audit::start($this->lab);

    livewire(ScanAudit::class, ['record' => $audit->id])
        ->call('scan', $this->lost->kode)
        ->assertSet('lastResult.title', 'Aset berstatus Hilang');

    expect($audit->items()->where('asset_id', $this->lost->id)->value('hasil'))->toBe(AuditResult::Ditemukan)
        ->and($this->lost->refresh()->status->nama)->toBe(Status::HILANG);
});

test('an asset not found can be marked lost after the session is closed, a found one cannot', function () {
    $audit = Audit::start($this->lab);
    $found = $audit->scan($this->laptop->kode);
    $audit->close();
    $notFound = $audit->items()->where('asset_id', $this->printer->id)->first();

    $notFound->markLost();

    expect($this->printer->refresh()->status->nama)->toBe(Status::HILANG)
        ->and(fn () => $found->markLost())->toThrow(LogicException::class, 'Tidak ditemukan');
});

test('an admin of another role does not see the session, its scan page, or its report', function () {
    Filament::setCurrentPanel('admin');
    $audit = Audit::start($this->lab);
    $audit->scan($this->laptop->kode);

    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));

    expect(Audit::query()->find($audit->id))->toBeNull();
    livewire(ListAudits::class)->assertCanNotSeeTableRecords([$audit]);
    $this->get(AuditResource::getUrl('view', ['record' => $audit]))->assertNotFound();
    $this->get(AuditResource::getUrl('scan', ['record' => $audit]))->assertNotFound();
    $this->get(route('filament.admin.audits.report', $audit))->assertNotFound();
});

test('an employee cannot open the stock opname menu', function () {
    $this->actingAs(userWithRole('karyawan', 'karyawan', ['company_id' => $this->tcm->id]));

    $this->get(AuditResource::getUrl('index'))->assertForbidden();
});

test('an admin starts a session from the panel and lands on the scan page', function () {
    Filament::setCurrentPanel('admin');

    livewire(CreateAudit::class)
        ->fillForm(['room_id' => $this->lab->id, 'catatan' => 'Opname akhir tahun'])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(AuditResource::getUrl('scan', ['record' => Audit::query()->sole()]));
});

test('the scan page records camera and typed codes and shows the latest result', function () {
    Filament::setCurrentPanel('admin');
    $this->withoutVite();
    $audit = Audit::start($this->lab);

    $this->get(AuditResource::getUrl('scan', ['record' => $audit]))->assertOk()->assertSee('Buka kamera');

    livewire(ScanAudit::class, ['record' => $audit->id])
        ->call('scan', url("/a/{$this->laptop->kode}"))
        ->assertSet('lastResult.title', 'Ditemukan')
        ->set('code', $this->projector->kode)
        ->call('submitCode')
        ->assertSet('lastResult.title', 'Salah tempat')
        ->assertSet('code', null)
        ->call('scan', $this->laptop->kode)
        ->assertSet('lastResult.title', 'Sudah discan')
        ->call('scan', $this->chair->kode)
        ->assertSet('lastResult.title', 'Tidak tercatat')
        ->assertSee($this->projector->kode);
});

test('the browser cannot overwrite the scan result shown on screen', function () {
    Filament::setCurrentPanel('admin');

    livewire(ScanAudit::class, ['record' => Audit::start($this->lab)->id])
        ->set('lastResult', ['tone' => 'success', 'title' => 'Ditemukan', 'body' => 'palsu']);
})->throws(CannotUpdateLockedPropertyException::class);

test('closing from the session page, then moving a misplaced asset and marking a missing one as lost', function () {
    Filament::setCurrentPanel('admin');
    $audit = Audit::start($this->lab);
    $audit->scan($this->laptop->kode);
    $misplaced = $audit->scan($this->projector->kode);

    livewire(ViewAudit::class, ['record' => $audit->id])
        ->callAction('close')
        ->assertNotified('Sesi ditutup');

    $missing = $audit->items()->where('asset_id', $this->printer->id)->sole();

    livewire(ItemsRelationManager::class, ['ownerRecord' => $audit->refresh(), 'pageClass' => ViewAudit::class])
        ->callAction(TestAction::make('move')->table($misplaced))
        ->assertNotified('Lokasi aset diperbarui')
        ->callAction(TestAction::make('markLost')->table($missing))
        ->assertNotified('Aset ditandai hilang');

    expect($this->projector->refresh()->room_id)->toBe($this->lab->id)
        ->and($this->printer->refresh()->status->nama)->toBe(Status::HILANG);
});

test('the report PDF downloads for the admin who manages the session', function () {
    $audit = Audit::start($this->lab);
    $audit->scan($this->laptop->kode);
    $audit->close();

    $this->get(route('filament.admin.audits.report', $audit))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('Stock-opname-Lab-'.now()->format('Y-m-d').'.pdf');
});

test('a super admin sets the audit interval per company', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->tcm->id]));

    livewire(Settings::class)
        ->assertSchemaStateSet(['audit_interval_bulan' => Audit::DEFAULT_INTERVAL_MONTHS])
        ->fillForm(['audit_interval_bulan' => 6])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('audit_interval_bulan', null, $this->tcm->id))->toBe(6);
});

test('the asset table filters assets that are due for an audit', function () {
    Filament::setCurrentPanel('admin');
    $this->laptop->update(['next_audit_date' => now()->addMonths(3)]);
    $this->printer->update(['next_audit_date' => now()->subDay()]);

    livewire(ListAssets::class)
        ->filterTable('perlu_audit', true)
        ->assertCanSeeTableRecords([$this->printer, $this->projector])
        ->assertCanNotSeeTableRecords([$this->laptop, $this->lost]);
});

test('the results of a session export per asset', function () {
    Filament::setCurrentPanel('admin');
    $audit = Audit::start($this->lab);
    $found = $audit->scan($this->laptop->kode);
    $exports = ExportAction::fake();

    livewire(ItemsRelationManager::class, ['ownerRecord' => $audit, 'pageClass' => ViewAudit::class])
        ->callAction(TestAction::make('export')->table())
        ->assertHasNoFormErrors();

    $exports->assertDispatched(AuditItemExporter::class, function (Export $export, Builder $query) use ($audit): bool {
        expect($query->pluck('audit_items.id')->sort()->values()->all())->toBe($audit->items()->orderBy('id')->pluck('id')->all());

        return true;
    });

    expect(AuditItemExporter::test(columnMap: ['asset.kode' => 'Kode', 'hasil' => 'Hasil'])->export($found))->toBe([$this->laptop->kode, 'Ditemukan']);
});

test('the asset page lists its audit history, also from sessions of another role', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->tcm->id]));
    $audit = Audit::start($this->lab);
    $item = $audit->scan($this->laptop->kode);

    $this->actingAs($this->adminIt);

    livewire(AuditItemsRelationManager::class, ['ownerRecord' => $this->laptop, 'pageClass' => ViewAsset::class])
        ->assertCanSeeTableRecords([$item])
        ->assertSee('Lab');
});
