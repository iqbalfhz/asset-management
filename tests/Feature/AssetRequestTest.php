<?php

use App\Enums\AssetRequestStatus;
use App\Enums\AssignmentStatus;
use App\Filament\Karyawan\Pages\MyAssetRequests;
use App\Filament\Karyawan\Pages\MyAssets;
use App\Filament\Resources\AssetRequests\AssetRequestResource;
use App\Filament\Resources\AssetRequests\Pages\ListAssetRequests;
use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Models\AssetRequest;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');

    $this->tcm = Company::factory()->create();
    $this->laptops = Category::factory()->create(['nama' => 'Laptop', 'role_id' => Role::findByName('admin_it')->id]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->rinaUser = userWithRole('karyawan', 'karyawan', ['company_id' => $this->tcm->id]);
    $this->rina = Employee::factory()->create(['company_id' => $this->tcm->id, 'user_id' => $this->rinaUser->id]);
    $this->spareLaptop = availableAsset($this->tcm, $this->laptops, ['requestable' => true]);
    $this->details = ['tanggal' => now()->toDateString(), 'kondisi' => 'Baik'];
});

function actAsRequester(User $user): void
{
    test()->actingAs($user);
    Filament::setCurrentPanel('karyawan');
}

function laptopRequest(AssetRequestStatus $status = AssetRequestStatus::Diajukan): AssetRequest
{
    return AssetRequest::factory()->create([
        'employee_id' => test()->rina->id,
        'category_id' => test()->laptops->id,
        'status' => $status,
    ]);
}

test('an employee requests a category with a requestable asset and its managing admin sees it waiting', function () {
    actAsRequester($this->rinaUser);

    livewire(MyAssetRequests::class)
        ->callAction('request', data: ['category_id' => $this->laptops->id, 'alasan' => 'Laptop lama sering mati'])
        ->assertNotified('Permintaan terkirim');

    $request = AssetRequest::withoutGlobalScopes()->sole();

    expect($request->status)->toBe(AssetRequestStatus::Diajukan)
        ->and($request->employee_id)->toBe($this->rina->id)
        ->and($request->category_id)->toBe($this->laptops->id)
        ->and($request->alasan)->toBe('Laptop lama sering mati');

    $this->actingAs($this->adminIt);
    Filament::setCurrentPanel('admin');

    $this->get(AssetRequestResource::getUrl())->assertOk()->assertSee('Laptop lama sering mati');
    livewire(ListAssetRequests::class)->assertCanSeeTableRecords([$request]);
    expect(AssetRequestResource::getNavigationBadge())->toBe('1');
});

test('only a category with a requestable asset in the employee\'s company, not written off, can be requested', function () {
    $adminGaRole = Role::findByName('admin_ga')->id;
    $chairs = Category::factory()->create(['role_id' => $adminGaRole]);
    availableAsset($this->tcm, $chairs);
    $monitors = Category::factory()->create(['role_id' => $adminGaRole]);
    availableAsset(Company::factory()->create(), $monitors, ['requestable' => true]);
    $phones = Category::factory()->create(['role_id' => $adminGaRole]);
    availableAsset($this->tcm, $phones, ['requestable' => true])->transitionTo(Status::named(Status::DIHAPUSKAN));

    expect(fn () => AssetRequest::submit($this->rina, $chairs, 'Kursi patah'))->toThrow(LogicException::class, 'tidak bisa diminta')
        ->and(fn () => AssetRequest::submit($this->rina, $monitors, 'Butuh monitor'))->toThrow(LogicException::class, 'tidak bisa diminta')
        ->and(fn () => AssetRequest::submit($this->rina, $phones, 'Butuh HP'))->toThrow(LogicException::class, 'tidak bisa diminta');

    actAsRequester($this->rinaUser);

    livewire(MyAssetRequests::class)
        ->callAction('request', data: ['category_id' => $chairs->id, 'alasan' => 'Kursi patah'])
        ->assertHasFormErrors(['category_id']);

    expect(AssetRequest::withoutGlobalScopes()->count())->toBe(0);
});

test('an open request blocks another request for the same category', function (AssetRequestStatus $status) {
    laptopRequest($status);

    expect(fn () => AssetRequest::submit($this->rina, $this->laptops, 'Masih butuh'))->toThrow(LogicException::class, 'belum selesai');
})->with([AssetRequestStatus::Diajukan, AssetRequestStatus::Disetujui]);

test('a closed request lets the employee ask for the same category again', function (AssetRequestStatus $status) {
    laptopRequest($status);

    AssetRequest::submit($this->rina, $this->laptops, 'Masih butuh');

    expect(AssetRequest::withoutGlobalScopes()->where('status', AssetRequestStatus::Diajukan)->count())->toBe(1);
})->with([AssetRequestStatus::Ditolak, AssetRequestStatus::Selesai]);

test('an inactive employee cannot request an asset', function () {
    $this->rina->update(['is_active' => false]);

    expect(fn () => AssetRequest::submit($this->rina, $this->laptops, 'Butuh laptop'))->toThrow(LogicException::class, 'nonaktif');
});

test('approving keeps an optional note and the request cannot be decided twice', function () {
    $request = laptopRequest();
    $this->actingAs($this->adminIt);

    livewire(ListAssetRequests::class)
        ->callAction(TestAction::make('approve')->table($request), data: ['catatan' => 'Ambil di ruang IT'])
        ->assertNotified('Permintaan disetujui');

    expect($request->refresh()->status)->toBe(AssetRequestStatus::Disetujui)
        ->and($request->catatan)->toBe('Ambil di ruang IT')
        ->and($request->diputuskan_oleh)->toBe($this->adminIt->id)
        ->and(fn () => $request->reject('Terlambat'))->toThrow(LogicException::class, 'Statusnya sudah Disetujui');
});

test('a rejection needs a reason, which the employee then reads', function () {
    $request = laptopRequest();
    $this->actingAs($this->adminIt);

    livewire(ListAssetRequests::class)
        ->callAction(TestAction::make('reject')->table($request), data: ['catatan' => ''])
        ->assertHasFormErrors(['catatan' => 'required']);

    expect(fn () => $request->reject(' '))->toThrow(LogicException::class, 'wajib');

    livewire(ListAssetRequests::class)
        ->callAction(TestAction::make('reject')->table($request), data: ['catatan' => 'Stok laptop habis'])
        ->assertNotified('Permintaan ditolak');

    expect($request->refresh()->status)->toBe(AssetRequestStatus::Ditolak);

    actAsRequester($this->rinaUser);

    livewire(MyAssetRequests::class)
        ->assertCanSeeTableRecords([$request])
        ->assertSee('Stok laptop habis');
});

test('handing over an approved request issues a BAST, and accepting it completes the request', function () {
    Storage::fake(Assignment::SIGNATURE_DISK);
    $request = laptopRequest(AssetRequestStatus::Disetujui);
    $this->actingAs($this->adminIt);

    livewire(ListAssetRequests::class)
        ->callAction(TestAction::make('handOver')->table($request), data: ['asset_id' => $this->spareLaptop->id] + $this->details)
        ->assertNotified('Serah-terima tersimpan');

    $assignment = Assignment::query()->sole();

    expect($request->refresh()->status)->toBe(AssetRequestStatus::Disetujui)
        ->and($request->assignment_id)->toBe($assignment->id)
        ->and($assignment->status)->toBe(AssignmentStatus::Menunggu)
        ->and($this->spareLaptop->refresh()->assigned->is($this->rina))->toBeTrue();

    livewire(ListAssetRequests::class)->assertActionHidden(TestAction::make('handOver')->table($request));

    actAsRequester($this->rinaUser);

    livewire(MyAssets::class)->callAction(TestAction::make('accept')->table($this->spareLaptop), data: ['setuju' => true, 'tanda_tangan' => signatureDataUrl()]);

    expect($request->refresh()->status)->toBe(AssetRequestStatus::Selesai);
});

test('a hand-over needs an asset of the requested category and waits while the last one is unaccepted', function () {
    $request = laptopRequest(AssetRequestStatus::Disetujui);
    $this->actingAs($this->adminIt);
    $desk = availableAsset($this->tcm, Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]));
    $secondLaptop = availableAsset($this->tcm, $this->laptops);

    expect(fn () => $request->handOver($desk, $this->details))->toThrow(LogicException::class, 'bukan dari kategori');

    $request->handOver($this->spareLaptop, $this->details);

    expect(fn () => $request->handOver($secondLaptop, $this->details))->toThrow(LogicException::class, 'menunggu diterima');

    // Aset yang salah ditarik kembali sebelum diterima; permintaan bisa diserahkan ulang.
    Assignment::takeBack(new Collection([$this->spareLaptop->refresh()]), $this->details);
    $request->handOver($secondLaptop, $this->details);

    expect($request->refresh()->assignment->asset_id)->toBe($secondLaptop->id);
});

test('a request stays with its managing admins and the requesting company', function () {
    $request = laptopRequest();

    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->tcm->id]));
    livewire(ListAssetRequests::class)->assertCanSeeTableRecords([$request]);

    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));
    livewire(ListAssetRequests::class)->assertCanNotSeeTableRecords([$request]);
    expect(AssetRequestResource::getNavigationBadge())->toBeNull();

    Setting::put('company_separation', true);
    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => Company::factory()->create()->id]));
    livewire(ListAssetRequests::class)->assertCanNotSeeTableRecords([$request]);
});

test('an admin role without the request permission cannot open the page', function () {
    $this->actingAs(userWithRole('admin_gudang', attributes: ['company_id' => $this->tcm->id]));

    $this->get(AssetRequestResource::getUrl())->assertForbidden();
});

test('an employee sees only their own requests', function () {
    $mine = laptopRequest();
    $budi = Employee::factory()->create(['company_id' => $this->tcm->id]);
    $budisRequest = AssetRequest::factory()->create(['employee_id' => $budi->id, 'category_id' => $this->laptops->id]);
    actAsRequester($this->rinaUser);

    livewire(MyAssetRequests::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$budisRequest]);
});

test('an account not linked to an employee record gets no request button', function () {
    actAsRequester(userWithRole('karyawan', 'karyawan', ['company_id' => $this->tcm->id]));

    livewire(MyAssetRequests::class)->assertActionHidden('request');
});

test('marking an asset as requestable puts its category on the request form', function () {
    $monitors = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $monitor = availableAsset($this->tcm, $monitors);
    $this->actingAs($this->adminIt);

    livewire(EditAsset::class, ['record' => $monitor->getRouteKey()])
        ->fillForm(['requestable' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Category::query()->requestableIn($this->tcm->id)->pluck('id')->sort()->values()->all())->toBe([$this->laptops->id, $monitors->id]);
});
