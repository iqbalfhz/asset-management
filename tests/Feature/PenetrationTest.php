<?php

use App\Enums\DisposalMethod;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Companies\CompanyResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Attachment;
use App\Models\Audit;
use App\Models\Category;
use App\Models\Company;
use App\Models\Consumable;
use App\Models\Disposal;
use App\Models\Employee;
use App\Models\License;
use App\Models\Maintenance;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// Serangan yang mencoba menembus batas otorisasi; semuanya harus gagal (ditolak/404/tidak berubah).
beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    $this->travelTo('2026-11-19 10:00');
    Storage::fake('local');

    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $this->other = Company::factory()->create(['kode' => 'OTH']);
    $this->itCat = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $this->gaCat = Category::factory()->create(['role_id' => Role::findByName('admin_ga')->id]);

    $this->itAdmin = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->gaAdmin = userWithRole('admin_ga', attributes: ['company_id' => $this->tcm->id]);
    $this->karyawanUser = userWithRole('karyawan', 'karyawan', ['company_id' => $this->tcm->id]);
    $this->karyawan = Employee::factory()->create(['company_id' => $this->tcm->id, 'user_id' => $this->karyawanUser->id]);

    $this->itAsset = availableAsset($this->tcm, $this->itCat, ['nama' => 'Laptop IT']);
});

function handOverBy(User $admin, Asset $asset, Employee $to): Assignment
{
    test()->actingAs($admin->refresh());
    $noBast = Assignment::handOver(new EloquentCollection([$asset]), $to, ['tanggal' => '2026-11-19', 'kondisi' => 'Baik', 'tanda_tangan' => signatureDataUrl()]);

    return Assignment::query()->where('no_bast', $noBast)->sole();
}

test('ATTACK IDOR: an admin cannot download documents of an asset in a category they do not manage', function () {
    $assignment = handOverBy($this->itAdmin, $this->itAsset, $this->karyawan);
    $attachment = Attachment::factory()->create(['attachable_type' => (new Asset)->getMorphClass(), 'attachable_id' => $this->itAsset->id, 'diunggah_oleh' => $this->itAdmin->id]);
    $maintenance = Maintenance::factory()->create(['asset_id' => $this->itAsset->id, 'foto' => 'maintenances/x.jpg']);

    $this->actingAs($this->gaAdmin->refresh());

    $this->get(route('filament.admin.bast.download', $assignment))->assertNotFound();
    $this->get(route('filament.admin.attachments.download', $attachment))->assertNotFound();
    $this->get(route('filament.admin.maintenances.photo', $maintenance))->assertNotFound();
    $this->get(route('filament.admin.labels', ['aset' => $this->itAsset->id]))->assertNotFound();
});

test('ATTACK IDOR: an employee cannot download another employee\'s BAST', function () {
    $otherEmp = Employee::factory()->create(['company_id' => $this->tcm->id]);
    $assignment = handOverBy($this->itAdmin, $this->itAsset, $otherEmp);

    $this->actingAs($this->karyawanUser->refresh());
    $this->get(route('filament.karyawan.bast.download', $assignment))->assertNotFound();
});

test('ATTACK IDOR: a cross-category disposal report and audit report are hidden', function () {
    $this->itAsset->transitionTo(Status::named(Status::RUSAK));
    $this->actingAs($this->itAdmin->refresh());
    $disposal = Disposal::submit($this->itAsset, ['alasan' => 'Rusak', 'metode' => DisposalMethod::Dibuang]);
    $this->actingAs(userWithRole('super_admin'));
    $disposal->approve();
    $this->actingAs($this->itAdmin->refresh());
    $audit = Audit::start(roomOf($this->tcm));

    $this->actingAs($this->gaAdmin->refresh());
    $this->get(route('filament.admin.disposals.report', $disposal))->assertNotFound();
    $this->get(route('filament.admin.audits.report', $audit))->assertNotFound();
});

test('ATTACK privilege escalation: only a super admin reaches Roles, Users, Companies and the backup download', function () {
    $urls = [
        UserResource::getUrl('index'),
        RoleResource::getUrl('index'),
        CompanyResource::getUrl('index'),
        route('filament.admin.filament-spatie-backup.download'),
    ];

    foreach ([$this->itAdmin, $this->gaAdmin, $this->karyawanUser] as $attacker) {
        $this->actingAs($attacker->refresh());
        foreach ($urls as $url) {
            expect($this->get($url)->status())->toBeIn([403, 404]);
        }
    }

    expect($this->itAdmin->can('viewAny', User::class))->toBeFalse()
        ->and($this->itAdmin->can('view_all_assets'))->toBeFalse()
        ->and($this->itAdmin->can('transfer_assets_between_companies'))->toBeFalse();
});

test('ATTACK privilege escalation: an employee cannot open the admin panel', function () {
    $this->actingAs($this->karyawanUser->refresh());
    expect($this->get('/admin')->status())->toBeIn([403, 302]);
    $this->get(AssetResource::getUrl('index'))->assertForbidden();
});

test('ATTACK broken object authorization: an admin cannot act on an asset outside their managed category', function () {
    // Policy ikut menegakkan batas kategori, bukan hanya global scope yang menyembunyikan barisnya.
    expect($this->gaAdmin->can('view', $this->itAsset))->toBeFalse()
        ->and($this->gaAdmin->can('update', $this->itAsset))->toBeFalse()
        ->and($this->gaAdmin->can('delete', $this->itAsset))->toBeFalse()
        ->and($this->itAdmin->can('update', $this->itAsset))->toBeTrue()
        ->and(userWithRole('super_admin')->can('update', $this->itAsset))->toBeTrue();
});

test('ATTACK mass assignment: asset status, company and code cannot be changed by a plain update', function () {
    // status_id tidak fillable: diabaikan diam-diam, status tidak berubah.
    $this->itAsset->update(['status_id' => Status::named(Status::DIHAPUSKAN)->id]);
    expect($this->itAsset->fresh('status')->status->nama)->toBe(Status::TERSEDIA);

    // company_id dan kode fillable (perlu saat create), tapi guard menolak perubahannya.
    expect(fn () => $this->itAsset->update(['company_id' => $this->other->id]))->toThrow(LogicException::class);
    expect(fn () => $this->itAsset->update(['kode' => 'HACK-0001']))->toThrow(LogicException::class);

    // Set status langsung pun ditolak di luar transaksi.
    $this->itAsset->status_id = Status::named(Status::DIHAPUSKAN)->id;
    expect(fn () => $this->itAsset->save())->toThrow(LogicException::class);
});

test('ATTACK stock tampering: consumable stock cannot be set directly, only through a transaction', function () {
    $consumable = Consumable::factory()->create(['company_id' => $this->tcm->id, 'role_id' => Role::findByName('admin_ga')->id]);
    $consumable->update(['stok' => 9999]);
    expect($consumable->fresh()->stok)->toBe(0);

    $consumable->stok = 9999;
    expect(fn () => $consumable->save())->toThrow(LogicException::class);
});

test('ATTACK stored XSS: a script in an asset name and serial is escaped in the BAST PDF', function () {
    $evil = availableAsset($this->tcm, $this->itCat, [
        'nama' => '<script>alert(document.cookie)</script>',
        'no_seri' => '"><img src=x onerror=alert(1)>',
    ]);
    $assignment = handOverBy($this->itAdmin, $evil, $this->karyawan);

    // Payload harus keluar sebagai teks ter-escape, bukan tag HTML yang hidup.
    renderBast($assignment)
        ->assertDontSee('<script>', false)
        ->assertDontSee('<img src=x', false)
        ->assertSee('&lt;script&gt;', false)
        ->assertSee('&lt;img src=x', false);
});

test('ATTACK API: a token reads only its owner\'s assets and cannot reach another category by code', function () {
    $token = $this->gaAdmin->createToken('t', ['baca-aset'])->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/assets/'.$this->itAsset->kode)->assertNotFound();
});

test('ATTACK API: a read-only token cannot hand over, and an inactive account is refused', function () {
    $gaAsset = availableAsset($this->tcm, $this->gaCat);
    $readToken = $this->gaAdmin->createToken('r', ['baca-aset'])->plainTextToken;

    $this->withToken($readToken)->postJson('/api/v1/assets/'.$gaAsset->kode.'/serah-terima', [
        'tujuan_jenis' => 'karyawan', 'tujuan_id' => $this->karyawan->id, 'tanggal' => '2026-11-19', 'kondisi' => 'Baik',
    ])->assertForbidden();

    $this->app['auth']->forgetGuards();
    $fullToken = $this->gaAdmin->createToken('f', ['baca-aset'])->plainTextToken;
    $this->gaAdmin->update(['is_active' => false]);
    expect($this->withToken($fullToken)->getJson('/api/v1/assets')->status())->toBeIn([401, 403]);
});

test('ATTACK API: handing an asset to a target in another company is rejected', function () {
    $gaAsset = availableAsset($this->tcm, $this->gaCat);
    $outsider = Employee::factory()->create(['company_id' => $this->other->id]);
    $token = $this->gaAdmin->createToken('t', ['serah-terima'])->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/assets/'.$gaAsset->kode.'/serah-terima', [
        'tujuan_jenis' => 'karyawan', 'tujuan_id' => $outsider->id, 'tanggal' => '2026-11-19', 'kondisi' => 'Baik',
    ])->assertStatus(422);

    expect($gaAsset->refresh()->status->nama)->toBe(Status::TERSEDIA);
});

test('ATTACK CORS: the API does not allow an arbitrary cross-origin website', function () {
    $header = $this->withHeaders(['Origin' => 'https://evil.example'])
        ->getJson('/api/v1/assets')
        ->headers->get('Access-Control-Allow-Origin');

    expect($header)->not->toBe('*')->not->toBe('https://evil.example');
});

test('ATTACK data at rest: license product key and 2FA secret are encrypted in the database', function () {
    $license = License::factory()->create(['company_id' => $this->tcm->id, 'role_id' => Role::findByName('admin_it')->id, 'product_key' => 'SECRET-KEY-12345']);

    $stored = DB::table('licenses')->where('id', $license->id)->value('product_key');
    expect($stored)->not->toBe('SECRET-KEY-12345')
        ->and(Crypt::decryptString($stored))->toBe('SECRET-KEY-12345');

    $secret = DB::table('breezy_sessions')->where('authenticatable_id', $this->itAdmin->id)->value('two_factor_secret');
    expect($secret)->not->toBeNull()
        ->and(fn () => Crypt::decryptString($secret))->not->toThrow(Exception::class);
});
