<?php

use App\Filament\Resources\Manufacturers\Pages\ManageManufacturers;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Manufacturer;
use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\Notification;
use Filament\Notifications\NotificationMailbox;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
});

test('deleting a record still in use shows a message instead of an error page', function () {
    $this->actingAs(userWithRole('admin_it'));
    $lenovo = AssetModel::factory()->create()->manufacturer;

    livewire(ManageManufacturers::class)
        ->callAction(TestAction::make('delete')->table($lenovo))
        ->assertNotified(__('Data tidak bisa dihapus'));

    expect(Manufacturer::find($lenovo->id))->not->toBeNull();
});

test('bulk delete removes unused records and explains the ones still in use', function () {
    $this->actingAs(userWithRole('admin_it'));
    $used = AssetModel::factory()->create()->manufacturer;
    $unused = Manufacturer::factory()->create();

    livewire(ManageManufacturers::class)
        ->selectTableRecords([$used, $unused])
        ->callAction(TestAction::make('delete')->table()->bulk());

    // Ambil notifikasi terkirim dengan cara yang sama seperti Notification::assertNotified().
    app(NotificationMailbox::class)->flush();
    $notifications = new Notifications;
    $notifications->mount();
    $notifications->pullNotifications();

    expect(Manufacturer::find($used->id))->not->toBeNull()
        ->and(Manufacturer::find($unused->id))->toBeNull()
        ->and($notifications->notifications->map(fn (Notification $notification): ?string => $notification->getBody())->implode(' '))
        ->toContain(__(':count data tidak bisa dihapus karena masih dipakai oleh data lain.', ['count' => 1]));
});

test('a role that still manages a category cannot be deleted', function () {
    $this->actingAs(userWithRole('super_admin'));
    $gudang = Role::create(['name' => 'admin_gudang', 'panel' => 'admin']);
    Category::factory()->create(['role_id' => $gudang->id]);

    livewire(ListRoles::class)
        ->callAction(TestAction::make('delete')->table($gudang))
        ->assertNotified(__('Data tidak bisa dihapus'));

    expect(Role::find($gudang->id))->not->toBeNull();
});

test('a record no longer in use is still deleted normally', function () {
    $this->actingAs(userWithRole('admin_it'));
    $unused = Manufacturer::factory()->create();

    livewire(ManageManufacturers::class)
        ->callAction(TestAction::make('delete')->table($unused))
        ->assertNotNotified(__('Data tidak bisa dihapus'));

    expect(Manufacturer::find($unused->id))->toBeNull();
});
