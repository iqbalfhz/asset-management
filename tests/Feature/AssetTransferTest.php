<?php

use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Filament\Resources\Assets\RelationManagers\TransfersRelationManager;
use App\Models\AssetTransfer;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Component;
use App\Models\Employee;
use App\Models\License;
use App\Models\Role;
use App\Models\Room;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');

    $this->efm = Company::factory()->create(['kode' => 'EFM', 'nama' => 'PT EFM']);
    $this->pakar = Company::factory()->create(['kode' => 'PKR', 'nama' => 'PT Pakar']);
    $this->efmRoom = roomOf($this->efm);
    $this->pakarRoom = roomOf($this->pakar);
    $this->laptops = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $this->laptop = availableAsset($this->efm, $this->laptops, ['room_id' => $this->efmRoom->id, 'default_room_id' => $this->efmRoom->id]);

    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->efm->id]));
});

function moveLaptopTo(Company $company, Room $room): void
{
    test()->laptop->transferTo($company, $room, today()->toDateString(), 'Dipakai tim PAKAR');
}

test('an available asset moves to another company with the same code, and the move is recorded on its page', function () {
    $code = $this->laptop->kode;

    livewire(ViewAsset::class, ['record' => $this->laptop->id])
        ->callAction('transferCompany', data: [
            'company_id' => $this->pakar->id,
            'room_id' => $this->pakarRoom->id,
            'tanggal' => today()->toDateString(),
            'alasan' => 'Dipakai tim PAKAR',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Aset dipindah ke perusahaan lain');

    $laptop = $this->laptop->refresh();
    $transfer = AssetTransfer::query()->sole();

    expect($laptop->kode)->toBe($code)
        ->and($laptop->company_id)->toBe($this->pakar->id)
        ->and($laptop->room_id)->toBe($this->pakarRoom->id)
        ->and($laptop->default_room_id)->toBe($this->pakarRoom->id)
        ->and($transfer->from_company_id)->toBe($this->efm->id)
        ->and($transfer->from_room_id)->toBe($this->efmRoom->id)
        ->and($transfer->alasan)->toBe('Dipakai tim PAKAR');

    livewire(TransfersRelationManager::class, ['ownerRecord' => $laptop, 'pageClass' => ViewAsset::class])
        ->assertCanSeeTableRecords([$transfer])
        ->assertSee(['PT EFM', 'PT Pakar']);
});

test('a move is refused while the asset is in use or carries other items, and for the wrong company or room', function () {
    $refuses = fn (string $message, ?Company $company = null, ?Room $room = null) => expect(fn () => moveLaptopTo($company ?? $this->pakar, $room ?? $this->pakarRoom))
        ->toThrow(LogicException::class, $message);

    $refuses('sudah milik PT EFM', $this->efm, $this->efmRoom);
    $refuses('berada di perusahaan lain', room: $this->efmRoom);

    $this->pakar->update(['is_active' => false]);
    $refuses('sudah nonaktif');
    $this->pakar->update(['is_active' => true]);

    $seat = License::factory()->create(['company_id' => $this->efm->id, 'nama_software' => 'AutoCAD LT'])->assignSeat($this->laptop);
    $refuses('masih membawa seat AutoCAD LT');
    $seat->release();

    $installation = Component::factory()->create(['company_id' => $this->efm->id, 'nama' => 'RAM 8 GB'])->install($this->laptop, 2);
    $refuses('masih membawa 2 × RAM 8 GB');
    $installation->release();

    $dock = availableAsset($this->efm, $this->laptops);
    Assignment::handOver(new Collection([$dock]), $this->laptop, ['tanggal' => today()->toDateString(), 'kondisi' => 'Baik']);
    $refuses("masih membawa {$dock->kode}");
    Assignment::takeBack(new Collection([$dock->refresh()]), ['tanggal' => today()->toDateString(), 'kondisi' => 'Baik']);

    Assignment::handOver(new Collection([$this->laptop->refresh()]), Employee::factory()->create(['company_id' => $this->efm->id]), ['tanggal' => today()->toDateString(), 'kondisi' => 'Baik']);
    $refuses('Hanya aset Disiapkan atau Tersedia');

    expect(AssetTransfer::query()->exists())->toBeFalse()
        ->and($this->laptop->refresh()->company_id)->toBe($this->efm->id);
});

test('the company of an asset only changes by moving it, not by editing', function () {
    expect(fn () => $this->laptop->update(['company_id' => $this->pakar->id]))
        ->toThrow(LogicException::class, 'Perusahaan aset hanya berubah lewat Pindah perusahaan.');

    livewire(EditAsset::class, ['record' => $this->laptop->id])->assertFormFieldDisabled('company_id');
});

test('moving assets between companies needs its own permission, which admins do not get by default', function () {
    $adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->efm->id]);
    $this->actingAs($adminIt);

    livewire(ViewAsset::class, ['record' => $this->laptop->id])->assertActionHidden('transferCompany');

    Role::findByName('admin_it')->givePermissionTo('transfer_assets_between_companies');
    $this->actingAs($adminIt->refresh());

    livewire(ViewAsset::class, ['record' => $this->laptop->id])->assertActionVisible('transferCompany');
});
