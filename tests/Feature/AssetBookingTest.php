<?php

use App\Enums\BookingStatus;
use App\Filament\Karyawan\Pages\MyBookings;
use App\Filament\Pages\Calendar;
use App\Filament\Resources\AssetBookings\AssetBookingResource;
use App\Filament\Resources\AssetBookings\Pages\ListAssetBookings;
use App\Models\AssetBooking;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Status;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    $this->travelTo('2026-11-19 08:00');

    $this->tcm = Company::factory()->create();
    $this->gadgets = Category::factory()->create(['role_id' => Role::findByName('admin_ga')->id]);
    $this->projector = availableAsset($this->tcm, $this->gadgets, ['nama' => 'Proyektor Epson', 'bookable' => true]);

    $this->rinaUser = userWithRole('karyawan', 'karyawan', ['company_id' => $this->tcm->id]);
    $this->rina = Employee::factory()->create(['company_id' => $this->tcm->id, 'user_id' => $this->rinaUser->id, 'nama' => 'Rina']);
    $this->budi = Employee::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Budi']);
});

function bookProjector(Employee $employee, string $from, string $to): AssetBooking
{
    return AssetBooking::book(test()->projector, $employee, Carbon::parse($from), Carbon::parse($to), 'Presentasi klien');
}

test('an employee books a free slot from the employee panel, and sees it with its status', function () {
    $this->actingAs($this->rinaUser);
    Filament::setCurrentPanel('karyawan');

    livewire(MyBookings::class)
        ->callAction('book', data: [
            'asset_id' => $this->projector->id,
            'mulai' => '2026-11-20 09:00',
            'selesai' => '2026-11-20 12:00',
            'keperluan' => 'Presentasi klien',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Barang terpesan');

    $booking = AssetBooking::withoutGlobalScopes()->sole();

    expect($booking->employee_id)->toBe($this->rina->id)
        ->and($booking->status())->toBe(BookingStatus::AkanDatang)
        ->and($this->projector->refresh()->status->nama)->toBe(Status::TERSEDIA);

    livewire(MyBookings::class)->assertCanSeeTableRecords([$booking])->assertSee('20 Nov 2026, 09:00–12:00');
});

test('a booking that overlaps another one is refused, while back-to-back, cancelled, and other-item bookings are fine', function () {
    $budis = bookProjector($this->budi, '2026-11-20 09:00', '2026-11-20 12:00');

    expect(fn () => bookProjector($this->rina, '2026-11-20 11:00', '2026-11-20 13:00'))
        ->toThrow(LogicException::class, 'sudah dipesan Budi pada 20 Nov 2026, 09:00–12:00');

    bookProjector($this->rina, '2026-11-20 08:00', '2026-11-20 09:00');
    bookProjector($this->rina, '2026-11-20 12:00', '2026-11-20 13:00');

    $camera = availableAsset($this->tcm, $this->gadgets, ['bookable' => true]);
    AssetBooking::book($camera, $this->rina, Carbon::parse('2026-11-20 10:00'), Carbon::parse('2026-11-20 11:00'), 'Dokumentasi acara');

    $budis->cancel();
    bookProjector($this->rina, '2026-11-20 10:00', '2026-11-20 11:00');

    expect(AssetBooking::query()->count())->toBe(5);
});

test('only bookable, available assets of the employee\'s own company can be booked, for a time ahead', function () {
    $refuses = fn (string $message, ?string $from = '2026-11-20 09:00', ?string $to = '2026-11-20 10:00', ?Employee $employee = null) => expect(fn () => bookProjector($employee ?? $this->rina, $from, $to))
        ->toThrow(LogicException::class, $message);

    $refuses('Jam selesai harus setelah jam mulai.', to: '2026-11-20 09:00');
    $refuses('Jam mulai sudah lewat.', '2026-11-19 07:00', '2026-11-19 09:00');
    $refuses('berada di perusahaan lain', employee: Employee::factory()->create(['nama' => 'Tamu']));

    $this->projector->update(['bookable' => false]);
    $refuses('tidak bisa dipesan');

    $this->projector->update(['bookable' => true]);
    $this->projector->transitionTo(Status::named(Status::RUSAK));
    $refuses('tidak bisa dipesan');
});

test('an employee cancels their booking, and an admin of the item cancels any booking with a reason', function () {
    $own = bookProjector($this->rina, '2026-11-20 09:00', '2026-11-20 10:00');
    $other = bookProjector($this->budi, '2026-11-21 09:00', '2026-11-21 10:00');

    $this->actingAs($this->rinaUser);
    Filament::setCurrentPanel('karyawan');

    livewire(MyBookings::class)
        ->callAction(TestAction::make('cancel')->table($own))
        ->assertNotified('Pesanan dibatalkan')
        ->assertActionHidden(TestAction::make('cancel')->table($own))
        ->assertCanNotSeeTableRecords([$other]);

    $adminGa = userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]);
    Role::findByName('admin_ga')->revokePermissionTo('Update:AssetBooking');
    $this->actingAs($adminGa->refresh());
    Filament::setCurrentPanel('admin');

    livewire(ListAssetBookings::class)->assertActionHidden(TestAction::make('cancel')->table($other));

    Role::findByName('admin_ga')->givePermissionTo('Update:AssetBooking');
    $this->actingAs($adminGa->refresh());

    livewire(ListAssetBookings::class)
        ->callAction(TestAction::make('cancel')->table($other), data: ['alasan_batal' => 'Proyektor dipakai rapat direksi']);

    expect($own->refresh()->status())->toBe(BookingStatus::Dibatalkan)
        ->and($other->refresh()->alasan_batal)->toBe('Proyektor dipakai rapat direksi')
        ->and(fn () => $own->cancel())->toThrow(LogicException::class, 'Pesanan ini sudah dibatalkan.');

    $finished = bookProjector($this->rina, '2026-11-22 09:00', '2026-11-22 10:00');
    $this->travelTo('2026-11-22 10:01');

    expect(fn () => $finished->cancel())->toThrow(LogicException::class, 'Pesanan yang sudah selesai tidak bisa dibatalkan.');
});

test('bookings show on the calendar and the bookings menu only for the admins who manage the item', function () {
    $booking = bookProjector($this->budi, '2026-11-20 09:00', '2026-11-20 12:00');
    Filament::setCurrentPanel('admin');

    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]));
    livewire(ListAssetBookings::class)->assertCanSeeTableRecords([$booking]);
    livewire(Calendar::class)->assertSee(['Peminjaman', 'Budi, 20 Nov 2026, 09:00–12:00']);

    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]));
    livewire(ListAssetBookings::class)->assertCanNotSeeTableRecords([$booking]);
    livewire(Calendar::class)->assertDontSee('Budi, 20 Nov');

    $this->actingAs($this->rinaUser)->get(AssetBookingResource::getUrl('index', panel: 'admin'))->assertForbidden();
});
