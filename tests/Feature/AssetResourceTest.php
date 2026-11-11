<?php

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Assets\Pages\CreateAsset;
use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\Role;
use App\Models\Room;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    $this->travelTo(now()->setDate(2026, 11, 11));
    Filament::setCurrentPanel('admin');

    $this->company = Company::factory()->create(['kode' => 'TCM']);
    $this->laptop = Category::factory()->create(['kode' => 'LPT', 'role_id' => Role::findByName('admin_it')->id, 'template_spesifikasi' => ['Prosesor', 'RAM']]);
    $this->kursi = Category::factory()->create(['kode' => 'KRS', 'role_id' => Role::findByName('admin_ga')->id]);

    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->company->id]));
});

function laptopAsset(array $attributes = []): Asset
{
    return Asset::factory()->create(['company_id' => test()->company->id, 'category_id' => test()->laptop->id, ...$attributes]);
}

test('admin IT creates an asset with an automatic code and the chosen start status', function () {
    livewire(CreateAsset::class)
        ->fillForm([
            'company_id' => $this->company->id,
            'nama' => 'Laptop Staf IT',
            'category_id' => $this->laptop->id,
            'status_id' => Status::named(Status::TERSEDIA)->id,
            'jumlah' => 1,
            'no_seri' => 'SN-001',
            'harga_beli' => 15500000,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $asset = Asset::firstWhere('nama', 'Laptop Staf IT');

    expect($asset->kode)->toBe('TCM-LPT-2026-00001')
        ->and($asset->status->nama)->toBe(Status::TERSEDIA)
        ->and($asset->harga_beli)->toBe(15500000);
});

test('choosing a category fills the specification keys from its template', function () {
    livewire(CreateAsset::class)
        ->fillForm(['category_id' => $this->laptop->id])
        ->assertFormSet(['spesifikasi' => ['Prosesor' => '', 'RAM' => '']]);
});

test('twenty assets are created at once with consecutive codes', function () {
    livewire(CreateAsset::class)
        ->fillForm([
            'company_id' => $this->company->id,
            'nama' => 'Monitor',
            'category_id' => $this->laptop->id,
            'status_id' => Status::named(Status::DISIAPKAN)->id,
            'jumlah' => 20,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Asset::where('nama', 'Monitor')->orderBy('kode')->pluck('kode')->all())
        ->toBe(collect(range(1, 20))->map(fn (int $number): string => sprintf('TCM-LPT-2026-%05d', $number))->all());
});

test('a model that requires serial numbers needs one per asset', function () {
    $model = AssetModel::factory()->create(['category_id' => $this->laptop->id, 'wajib_nomor_seri' => true]);
    $data = [
        'company_id' => $this->company->id,
        'nama' => 'ThinkPad',
        'category_id' => $this->laptop->id,
        'asset_model_id' => $model->id,
        'status_id' => Status::named(Status::DISIAPKAN)->id,
        'jumlah' => 3,
    ];

    livewire(CreateAsset::class)
        ->fillForm([...$data, 'nomor_seri_banyak' => "SN-1\nSN-2"])
        ->call('create')
        ->assertHasFormErrors(['nomor_seri_banyak']);

    livewire(CreateAsset::class)
        ->fillForm([...$data, 'nomor_seri_banyak' => "SN-1\nSN-2\nSN-3"])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Asset::where('nama', 'ThinkPad')->orderBy('kode')->pluck('no_seri')->all())->toBe(['SN-1', 'SN-2', 'SN-3']);
});

test('an admin cannot pick a category managed by another role', function () {
    livewire(CreateAsset::class)
        ->fillForm([
            'company_id' => $this->company->id,
            'nama' => 'Kursi',
            'category_id' => $this->kursi->id,
            'status_id' => Status::named(Status::DISIAPKAN)->id,
            'jumlah' => 1,
        ])
        ->call('create')
        ->assertHasFormErrors(['category_id']);

    expect(Asset::count())->toBe(0);
});

test('cloning gives a new code, empties the serial number and starts as Disiapkan', function () {
    $original = laptopAsset(['no_seri' => 'SN-ASLI']);

    livewire(ListAssets::class)->callAction(TestAction::make('replicate')->table($original));

    $clone = Asset::query()->whereKeyNot($original->id)->sole();

    expect($clone->kode)->toBe('TCM-LPT-2026-00002')
        ->and($clone->no_seri)->toBeNull()
        ->and($clone->status->nama)->toBe(Status::DISIAPKAN)
        ->and($clone->nama)->toBe($original->nama);
});

test('bulk status change only moves assets between Disiapkan and Tersedia', function () {
    $prepared = laptopAsset(['status_id' => Status::named(Status::DISIAPKAN)->id]);
    $inUse = laptopAsset();
    DB::table('assets')->where('id', $inUse->id)->update(['status_id' => Status::named('Dipakai')->id]);

    livewire(ListAssets::class)
        ->selectTableRecords([$prepared, $inUse])
        ->callAction(TestAction::make('markAs')->table()->bulk(), data: ['status_id' => Status::named(Status::TERSEDIA)->id]);

    expect($prepared->refresh()->status->nama)->toBe(Status::TERSEDIA)
        ->and($inUse->refresh()->status->nama)->toBe('Dipakai');
});

test('bulk actions move assets to another room and category without changing the code', function () {
    $assets = collect([laptopAsset(), laptopAsset()]);
    $room = Room::factory()->create();
    $mouse = Category::factory()->create(['kode' => 'MSE', 'role_id' => Role::findByName('admin_it')->id]);

    livewire(ListAssets::class)
        ->selectTableRecords($assets)
        ->callAction(TestAction::make('moveRoom')->table()->bulk(), data: ['room_id' => $room->id])
        ->selectTableRecords($assets)
        ->callAction(TestAction::make('changeCategory')->table()->bulk(), data: ['category_id' => $mouse->id]);

    $assets->each(function (Asset $asset) use ($room, $mouse): void {
        $kode = $asset->kode;
        $asset->refresh();

        expect($asset->room_id)->toBe($room->id)
            ->and($asset->category_id)->toBe($mouse->id)
            ->and($asset->kode)->toBe($kode);
    });
});

test('assets have no delete action and status is not editable on the edit page', function () {
    $asset = laptopAsset();

    livewire(ListAssets::class)->assertActionDoesNotExist(TestAction::make('delete')->table($asset));

    livewire(EditAsset::class, ['record' => $asset->getRouteKey()])
        ->assertActionDoesNotExist('delete')
        ->assertFormFieldHidden('status_id');
});

test('the asset pages render for admin IT', function () {
    $asset = laptopAsset(['asset_model_id' => AssetModel::factory()->create(['category_id' => $this->laptop->id])->id, 'room_id' => Room::factory()->create()->id]);

    $this->get(AssetResource::getUrl('index'))->assertOk()->assertSee($asset->kode);
    $this->get(AssetResource::getUrl('create'))->assertOk();
    $this->get(AssetResource::getUrl('edit', ['record' => $asset]))->assertOk();
});

test('admin GA cannot open an IT asset and karyawan cannot open the asset list', function () {
    $asset = laptopAsset();

    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->company->id]));
    $this->get(AssetResource::getUrl('edit', ['record' => $asset]))->assertNotFound();

    $this->actingAs(userWithRole('karyawan', 'karyawan'));
    $this->get(AssetResource::getUrl('index'))->assertForbidden();
});
