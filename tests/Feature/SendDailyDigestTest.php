<?php

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
    $this->freezeTime();

    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $this->laptops = Category::factory()->create(['nama' => 'Laptop', 'role_id' => Role::findByName('admin_it')->id]);
    $this->chairs = Category::factory()->create(['nama' => 'Kursi', 'role_id' => Role::findByName('admin_ga')->id]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->adminGa = userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]);
});

// Isi ringkasan yang diterima user sebagai teks (judul lalu barisnya), atau null kalau tidak dikirim.
function digestFor(User $user): ?string
{
    $notices = Notification::sent($user, Notice::class);

    return $notices->isEmpty() ? null : collect($notices->sole()->sections)
        ->map(fn (array $lines, string $heading): string => $heading."\n".implode("\n", $lines))
        ->implode("\n\n");
}

test('a manager gets one digest of what needs follow-up, only from what they manage and within the day limits', function () {
    $soon = availableAsset($this->tcm, $this->laptops, ['garansi_sampai' => today()->addDays(30), 'eol_date' => today()->addDays(60), 'next_audit_date' => today()->subDay()]);
    $later = availableAsset($this->tcm, $this->laptops, ['garansi_sampai' => today()->addDays(90), 'eol_date' => today()->addDays(120), 'next_audit_date' => today()->addDays(30)]);
    $chair = availableAsset($this->tcm, $this->chairs, ['garansi_sampai' => today()->addDays(10)]);
    License::factory()->create(['company_id' => $this->tcm->id, 'nama_software' => 'AutoCAD LT', 'tgl_habis' => today()->addDays(20)]);
    Consumable::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Toner HP 85A', 'satuan' => 'pcs']);

    $this->artisan('notifikasi:harian')->assertSuccessful();

    expect(digestFor($this->adminIt))
        ->toContain("Garansi habis dalam 60 hari\n{$soon->kode}")
        ->toContain("Lisensi habis dalam 60 hari\nAutoCAD LT")
        ->toContain("Stok menipis\nToner HP 85A: sisa 0 pcs (minimum 5)")
        ->toContain("EOL dalam 90 hari\n{$soon->kode}")
        ->toContain("Audit jatuh tempo\n{$soon->kode}")
        ->not->toContain($later->kode)
        ->not->toContain($chair->kode);

    expect(digestFor($this->adminGa))
        ->toContain("Garansi habis dalam 60 hari\n{$chair->kode}")
        ->not->toContain($soon->kode)
        ->not->toContain('AutoCAD LT');
});

test('loans due within three days and hand-overs unaccepted for over two days reach the manager and the employee', function () {
    $rinaUser = userWithRole('karyawan', 'karyawan', ['company_id' => $this->tcm->id]);
    $rina = Employee::factory()->create(['company_id' => $this->tcm->id, 'user_id' => $rinaUser->id, 'nama' => 'Rina']);
    $loaned = availableAsset($this->tcm, $this->laptops);
    $fresh = availableAsset($this->tcm, $this->laptops);
    $this->actingAs($this->adminIt);

    $this->travel(-3)->days();
    Assignment::handOver(new Collection([$loaned]), $rina, ['tanggal' => today()->toDateString(), 'kondisi' => 'Baik', 'expected_return_at' => today()->addDays(5)->toDateString()]);
    $this->travel(3)->days();
    Assignment::handOver(new Collection([$fresh]), $rina, ['tanggal' => today()->toDateString(), 'kondisi' => 'Baik']);

    $this->artisan('notifikasi:harian')->assertSuccessful();

    expect(digestFor($this->adminIt))
        ->toContain("Pinjaman jatuh tempo\n{$loaned->kode}")
        ->toContain("Serah-terima belum diterima lebih dari 2 hari\n")
        ->toContain('Rina')
        ->not->toContain($fresh->kode);

    expect(digestFor($rinaUser))
        ->toContain("Serah-terima menunggu Anda terima\n")
        ->toContain($fresh->kode)
        ->toContain("Pinjaman Anda yang jatuh tempo\n{$loaned->kode}")
        ->not->toContain('Garansi');
});

test('nothing to follow up sends nothing, and nothing is sent while the setting is off', function () {
    $this->artisan('notifikasi:harian')->assertSuccessful();

    Notification::assertNothingSent();

    availableAsset($this->tcm, $this->laptops, ['garansi_sampai' => today()->addDays(30)]);
    Setting::put('email_notifikasi', false);

    $this->artisan('notifikasi:harian')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a long list stops at ten lines, and inactive managers or managers without email get nothing', function () {
    $inactive = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id, 'is_active' => false]);
    $withoutEmail = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id, 'email' => null]);
    Asset::factory()->count(12)->create([
        'company_id' => $this->tcm->id,
        'category_id' => $this->laptops->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
        'garansi_sampai' => today()->addDays(30),
    ]);

    $this->artisan('notifikasi:harian')->assertSuccessful();

    $warranties = Notification::sent($this->adminIt, Notice::class)->sole()->sections['Garansi habis dalam 60 hari'];

    expect($warranties)->toHaveCount(11)
        ->and(end($warranties))->toBe('dan 2 lainnya');

    Notification::assertNotSentTo([$inactive, $withoutEmail], Notice::class);
});
