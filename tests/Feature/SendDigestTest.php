<?php

use App\Enums\DigestFrequency;
use App\Enums\Language;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Consumable;
use App\Models\Employee;
use App\Models\License;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Status;
use App\Models\User;
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
    // Senin, 1 Februari 2027: jadwal mingguan dan bulanan sama-sama jatuh hari ini.
    $this->travelTo('2027-02-01 07:00');

    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $this->laptops = Category::factory()->create(['nama' => 'Laptop', 'role_id' => Role::findByName('admin_it')->id]);
    $this->chairs = Category::factory()->create(['nama' => 'Kursi', 'role_id' => Role::findByName('admin_ga')->id]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id, 'digest_frequency' => DigestFrequency::Mingguan]);
    $this->adminGa = userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id, 'digest_frequency' => DigestFrequency::Mingguan]);
});

// Isi ringkasan yang diterima user sebagai teks (judul lalu barisnya), atau null kalau tidak dikirim.
function digestFor(User $user): ?string
{
    $notices = Notification::sent($user, Notice::class);

    return $notices->isEmpty() ? null : collect($notices->sole()->sections)
        ->map(fn (array $lines, string $heading): string => $heading."\n".implode("\n", $lines))
        ->implode("\n\n");
}

function daysAhead(int $days): string
{
    return today()->addDays($days)->translatedFormat('j M Y');
}

test('a weekly digest lists what needs follow-up, only from what the manager handles and within the usual limits', function () {
    $soon = availableAsset($this->tcm, $this->laptops, ['garansi_sampai' => today()->addDays(30), 'eol_date' => today()->addDays(60), 'next_audit_date' => today()->subDay()]);
    $later = availableAsset($this->tcm, $this->laptops, ['garansi_sampai' => today()->addDays(90), 'eol_date' => today()->addDays(120), 'next_audit_date' => today()->addDays(30)]);
    $chair = availableAsset($this->tcm, $this->chairs, ['garansi_sampai' => today()->addDays(10)]);
    License::factory()->create(['company_id' => $this->tcm->id, 'nama_software' => 'AutoCAD LT', 'tgl_habis' => today()->addDays(20)]);
    Consumable::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Toner HP 85A', 'satuan' => 'pcs']);

    $this->artisan('notifikasi:ringkasan')->assertSuccessful();

    Notification::assertSentTo($this->adminIt, Notice::class, fn (Notice $notice): bool => $notice->subject === 'Ringkasan mingguan 1 Februari 2027');

    expect(digestFor($this->adminIt))
        ->toContain('Garansi habis sampai '.daysAhead(60)."\n{$soon->kode}")
        ->toContain('Lisensi habis sampai '.daysAhead(60)."\nAutoCAD LT")
        ->toContain("Stok menipis\nToner HP 85A: sisa 0 pcs (minimum 5)")
        ->toContain('EOL sampai '.daysAhead(90)."\n{$soon->kode}")
        ->toContain('Audit jatuh tempo sampai '.daysAhead(14)."\n{$soon->kode}")
        ->not->toContain($later->kode)
        ->not->toContain($chair->kode);

    expect(digestFor($this->adminGa))
        ->toContain('Garansi habis sampai '.daysAhead(60)."\n{$chair->kode}")
        ->not->toContain($soon->kode)
        ->not->toContain('AutoCAD LT');
});

test('a yearly digest looks a whole year ahead', function () {
    $this->travelTo('2027-01-01 07:00');
    $this->adminIt->update(['digest_frequency' => DigestFrequency::Tahunan]);
    $nextAutumn = availableAsset($this->tcm, $this->laptops, ['garansi_sampai' => today()->addDays(200), 'next_audit_date' => today()->addDays(300)]);

    $this->artisan('notifikasi:ringkasan')->assertSuccessful();

    Notification::assertSentTo($this->adminIt, Notice::class, fn (Notice $notice): bool => $notice->subject === 'Ringkasan tahunan 2027');

    expect(digestFor($this->adminIt))
        ->toContain("Garansi habis sampai 1 Jan 2028\n{$nextAutumn->kode}")
        ->toContain("Audit jatuh tempo sampai 1 Jan 2028\n{$nextAutumn->kode}");
});

test('each user gets the digest only on the day their choice falls on', function (DigestFrequency $frequency, string $day, bool $sent) {
    $this->travelTo("{$day} 07:00");
    $this->adminIt->update(['digest_frequency' => $frequency]);
    availableAsset($this->tcm, $this->laptops, ['garansi_sampai' => today()->addDays(10)]);

    $this->artisan('notifikasi:ringkasan')->assertSuccessful();

    expect(digestFor($this->adminIt) !== null)->toBe($sent);
})->with([
    'weekly on Monday' => [DigestFrequency::Mingguan, '2027-02-08', true],
    'weekly on Tuesday' => [DigestFrequency::Mingguan, '2027-02-09', false],
    'monthly on the 1st' => [DigestFrequency::Bulanan, '2027-03-01', true],
    'monthly on another Monday' => [DigestFrequency::Bulanan, '2027-02-08', false],
    'yearly on 1 January' => [DigestFrequency::Tahunan, '2027-01-01', true],
    'yearly on 1 February' => [DigestFrequency::Tahunan, '2027-02-01', false],
    'switched off on 1 January' => [DigestFrequency::Mati, '2027-01-01', false],
]);

test('loans due before the next digest and hand-overs unaccepted for over two days reach the manager and the employee', function () {
    $rinaUser = userWithRole('karyawan', 'karyawan', ['company_id' => $this->tcm->id, 'digest_frequency' => DigestFrequency::Mingguan]);
    $rina = Employee::factory()->create(['company_id' => $this->tcm->id, 'user_id' => $rinaUser->id, 'nama' => 'Rina']);
    $loaned = availableAsset($this->tcm, $this->laptops);
    $fresh = availableAsset($this->tcm, $this->laptops);
    $this->actingAs($this->adminIt);

    $this->travel(-3)->days();
    Assignment::handOver(new Collection([$loaned]), $rina, ['tanggal' => today()->toDateString(), 'kondisi' => 'Baik', 'expected_return_at' => today()->addDays(9)->toDateString()]);
    $this->travel(3)->days();
    Assignment::handOver(new Collection([$fresh]), $rina, ['tanggal' => today()->toDateString(), 'kondisi' => 'Baik']);

    $this->artisan('notifikasi:ringkasan')->assertSuccessful();

    expect(digestFor($this->adminIt))
        ->toContain('Pinjaman jatuh tempo sampai '.daysAhead(7)."\n{$loaned->kode}")
        ->toContain("Serah-terima belum diterima lebih dari 2 hari\n")
        ->toContain('Rina')
        ->not->toContain($fresh->kode);

    expect(digestFor($rinaUser))
        ->toContain("Serah-terima menunggu Anda terima\n")
        ->toContain($fresh->kode)
        ->toContain('Pinjaman Anda yang jatuh tempo sampai '.daysAhead(7)."\n{$loaned->kode}")
        ->not->toContain('Garansi');
});

test('nothing to follow up sends nothing, and nothing is sent while the setting is off', function () {
    $this->artisan('notifikasi:ringkasan')->assertSuccessful();

    Notification::assertNothingSent();

    availableAsset($this->tcm, $this->laptops, ['garansi_sampai' => today()->addDays(30)]);
    Setting::put('email_notifikasi', false);

    $this->artisan('notifikasi:ringkasan')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a long list stops at ten lines, and inactive managers or managers without email get nothing', function () {
    $inactive = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id, 'is_active' => false, 'digest_frequency' => DigestFrequency::Mingguan]);
    $withoutEmail = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id, 'email' => null, 'digest_frequency' => DigestFrequency::Mingguan]);
    Asset::factory()->count(12)->create([
        'company_id' => $this->tcm->id,
        'category_id' => $this->laptops->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
        'garansi_sampai' => today()->addDays(30),
    ]);

    $this->artisan('notifikasi:ringkasan')->assertSuccessful();

    $warranties = Notification::sent($this->adminIt, Notice::class)->sole()->sections['Garansi habis sampai '.daysAhead(60)];

    expect($warranties)->toHaveCount(11)
        ->and(end($warranties))->toBe('dan 2 lainnya');

    Notification::assertNotSentTo([$inactive, $withoutEmail], Notice::class);
});

test('a user who picked English gets the digest in English', function () {
    $this->adminIt->update(['locale' => Language::English]);
    availableAsset($this->tcm, $this->laptops, ['garansi_sampai' => today()->addDays(30)]);

    $this->artisan('notifikasi:ringkasan')->assertSuccessful();

    Notification::assertSentTo($this->adminIt, Notice::class, fn (Notice $notice): bool => $notice->subject === 'Weekly summary 1 February 2027');

    expect(digestFor($this->adminIt))->toContain('Warranties ending by 2 Apr 2027');
    expect(digestFor($this->adminGa))->toBeNull();
});
