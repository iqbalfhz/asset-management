<?php

use App\Models\Asset;
use App\Models\AssetRequest;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Maintenance;
use App\Models\Role;
use App\Models\Setting;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\Notice;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');
    Notification::fake();

    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $this->laptops = Category::factory()->create(['nama' => 'Laptop', 'role_id' => Role::findByName('admin_it')->id]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->adminGa = userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]);
    $this->rinaUser = userWithRole('karyawan', 'karyawan', ['company_id' => $this->tcm->id]);
    $this->rina = Employee::factory()->create(['company_id' => $this->tcm->id, 'user_id' => $this->rinaUser->id, 'nama' => 'Rina']);
});

function laptopHeldByRina(): Asset
{
    $laptop = availableAsset(test()->tcm, test()->laptops);
    test()->actingAs(test()->adminIt);
    Assignment::handOver(new Collection([$laptop]), test()->rina, ['tanggal' => now()->toDateString(), 'kondisi' => 'Baik']);

    return $laptop->refresh();
}

test('a damage report emails only the active admins with email who manage the asset category', function () {
    $inactiveIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id, 'is_active' => false]);
    $itWithoutEmail = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id, 'email' => null]);
    $laptop = laptopHeldByRina();
    $this->actingAs($this->rinaUser);

    Maintenance::report($this->rina, $laptop, 'Layar berkedip');

    Notification::assertSentTo($this->adminIt, Notice::class, fn (Notice $notice): bool => $notice->subject === "Laporan kerusakan baru: {$laptop->kode}"
        && $notice->sections === ['Keluhan' => ['Layar berkedip']]);
    Notification::assertNotSentTo([$this->adminGa, $inactiveIt, $itWithoutEmail, $this->rinaUser], Notice::class);
});

test('with separation on, a new asset request is not emailed to managers of another company', function () {
    Setting::put('company_separation', true);
    $otherCompanyIt = userWithRole('admin_it', attributes: ['company_id' => Company::factory()->create()->id]);
    availableAsset($this->tcm, $this->laptops, ['requestable' => true]);

    AssetRequest::submit($this->rina, $this->laptops, 'Laptop lama sering mati');

    Notification::assertSentTo($this->adminIt, Notice::class, fn (Notice $notice): bool => $notice->subject === 'Permintaan aset baru: Laptop'
        && $notice->sections === ['Alasan' => ['Laptop lama sering mati']]);
    Notification::assertNotSentTo([$otherCompanyIt, $this->adminGa], Notice::class);
});

test('the employee is emailed the decision on their request with the admin note', function (string $decide, string $note, string $subject) {
    $request = AssetRequest::factory()->create(['employee_id' => $this->rina->id, 'category_id' => $this->laptops->id]);
    $this->actingAs($this->adminIt);

    $request->{$decide}($note);

    Notification::assertSentTo($this->rinaUser, Notice::class, fn (Notice $notice): bool => $notice->subject === $subject
        && $notice->sections === ['Catatan admin' => [$note]]
        && str_ends_with($notice->actionUrl, '/karyawan/permintaan'));
})->with([
    'approved' => ['approve', 'Ambil di ruang IT', 'Permintaan Laptop disetujui'],
    'rejected' => ['reject', 'Stok laptop habis', 'Permintaan Laptop ditolak'],
]);

test('no notification email is sent while the setting is off', function () {
    Setting::put('email_notifikasi', false);
    $laptop = laptopHeldByRina();
    $this->actingAs($this->rinaUser);

    Maintenance::report($this->rina, $laptop, 'Layar berkedip');

    Notification::assertNothingSent();
});

test('the email is sent after the response and shows the greeting, the list and the button', function () {
    $notice = new Notice('Subjek', 'Pembuka email.', ['Keluhan' => ['Layar <berkedip>']], 'Buka daftar', 'https://asset.test/admin');

    $html = (string) $notice->toMail($this->adminIt)->render();

    expect($notice->viaConnections())->toBe(['mail' => 'deferred', TelegramChannel::class => 'deferred'])
        ->and($html)->toContain(e("Halo {$this->adminIt->name},"))
        ->toContain('Pembuka email.')
        ->toContain('Layar &lt;berkedip&gt;')
        ->toContain('href="https://asset.test/admin"');
});
