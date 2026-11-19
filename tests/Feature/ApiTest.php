<?php

use App\Enums\AssignmentStatus;
use App\Livewire\ApiTokens;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    Filament::setCurrentPanel('admin');

    $this->tcm = Company::factory()->create(['kode' => 'TCM']);
    $this->laptops = Category::factory()->create(['nama' => 'Laptop', 'role_id' => Role::findByName('admin_it')->id]);
    $this->adminIt = userWithRole('admin_it', attributes: ['company_id' => $this->tcm->id]);
    $this->laptop = availableAsset($this->tcm, $this->laptops);
    $this->rina = Employee::factory()->create(['company_id' => $this->tcm->id, 'nama' => 'Rina']);
});

/**
 * @param  array<int, string>  $abilities
 */
function apiToken(User $user, array $abilities = ['baca-aset', 'serah-terima']): string
{
    return $user->createToken('integrasi', $abilities, now()->addMonth())->plainTextToken;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function handOverPayload(array $overrides = []): array
{
    return $overrides + [
        'tujuan_jenis' => 'karyawan',
        'tujuan_id' => test()->rina->id,
        'tanggal' => today()->toDateString(),
        'kondisi' => 'Baik',
    ];
}

test('without a valid token the API answers 401', function () {
    $this->getJson('/api/v1/assets')->assertUnauthorized();
    $this->get('/api/v1/assets', ['Accept' => 'text/html'])->assertUnauthorized();
    $this->withToken('1|bukan-token')->getJson('/api/v1/assets')->assertUnauthorized();
});

test('a token reads only the assets its owner may see in the panel', function () {
    $desk = availableAsset($this->tcm, Category::factory()->create(['role_id' => Role::findByName('admin_ga')->id]));
    $this->withToken(apiToken($this->adminIt));

    $this->getJson('/api/v1/assets')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.kode', $this->laptop->kode)
        ->assertJsonPath('data.0.kategori', 'Laptop')
        ->assertJsonPath('data.0.status', 'Tersedia');

    $this->getJson("/api/v1/assets/{$this->laptop->kode}")->assertOk()->assertJsonPath('data.perusahaan', 'TCM');
    $this->getJson("/api/v1/assets/{$desk->kode}")->assertNotFound();
});

test('a token without the needed ability is refused', function () {
    $this->withToken(apiToken($this->adminIt, ['serah-terima']))->getJson('/api/v1/assets')->assertForbidden();

    $this->app['auth']->forgetGuards();

    $this->withToken(apiToken($this->adminIt, ['baca-aset']))
        ->postJson("/api/v1/assets/{$this->laptop->kode}/serah-terima", handOverPayload())
        ->assertForbidden();
});

test('a hand over through the API issues a BAST waiting for the employee, like the panel', function () {
    $this->withToken(apiToken($this->adminIt))
        ->postJson("/api/v1/assets/{$this->laptop->kode}/serah-terima", handOverPayload(['tanggal_kembali' => today()->addWeek()->toDateString()]))
        ->assertCreated()
        ->assertJsonPath('no_bast', 'BAST/TCM/'.now()->year.'/0001')
        ->assertJsonPath('data.status', 'Dipakai')
        ->assertJsonPath('data.pemegang.jenis', 'karyawan')
        ->assertJsonPath('data.pemegang.id', $this->rina->id);

    $assignment = Assignment::query()->sole();

    expect($assignment->status)->toBe(AssignmentStatus::Menunggu)
        ->and($assignment->created_by)->toBe($this->adminIt->id)
        ->and($assignment->expected_return_at->toDateString())->toBe(today()->addWeek()->toDateString());
});

test('a hand over follows the same rules as the panel', function () {
    $this->withToken(apiToken($this->adminIt));
    $url = "/api/v1/assets/{$this->laptop->kode}/serah-terima";

    $this->postJson($url, ['tujuan_jenis' => 'gudang'])->assertUnprocessable()->assertJsonValidationErrors(['tujuan_jenis', 'tujuan_id', 'tanggal', 'kondisi']);

    $this->rina->update(['is_active' => false]);
    $this->postJson($url, handOverPayload())->assertUnprocessable()->assertJsonValidationErrors(['tujuan_id']);

    $this->rina->update(['is_active' => true]);
    $this->postJson($url, handOverPayload())->assertCreated();
    $this->postJson($url, handOverPayload())->assertUnprocessable()->assertJsonValidationErrors(['aset']);
});

test('a hand over needs the Shield permission to change assets', function () {
    Role::findByName('admin_it')->revokePermissionTo('Update:Asset');

    $this->withToken(apiToken($this->adminIt))
        ->postJson("/api/v1/assets/{$this->laptop->kode}/serah-terima", handOverPayload())
        ->assertForbidden();

    expect(Assignment::query()->exists())->toBeFalse();
});

test('tokens stop working for accounts that may not open the admin panel', function (User $owner) {
    $this->withToken(apiToken($owner))->getJson('/api/v1/assets')->assertUnauthorized();
})->with([
    'inactive admin' => fn () => userWithRole('admin_it', attributes: ['company_id' => test()->tcm->id, 'is_active' => false]),
    'employee account' => fn () => userWithRole('karyawan', 'karyawan', ['company_id' => test()->tcm->id]),
]);

test('a token gets 60 requests a minute', function () {
    $this->withToken(apiToken($this->adminIt));

    foreach (range(1, 60) as $request) {
        $this->getJson("/api/v1/assets/{$this->laptop->kode}")->assertOk();
    }

    $this->getJson("/api/v1/assets/{$this->laptop->kode}")->assertTooManyRequests();
});

test('a token made in Profile must expire within a year', function () {
    $this->actingAs($this->adminIt);
    $create = TestAction::make('createToken')->table();
    $data = ['token_name' => 'Integrasi gudang', 'abilities' => ['baca-aset']];

    livewire(ApiTokens::class)->callAction($create, $data)->assertHasFormErrors(['expires_at' => 'required']);
    livewire(ApiTokens::class)->callAction($create, $data + ['expires_at' => today()->addYears(2)->toDateString()])->assertHasFormErrors(['expires_at']);
    livewire(ApiTokens::class)->callAction($create, $data + ['expires_at' => today()->addMonths(6)->toDateString()])->assertHasNoFormErrors();

    expect(PersonalAccessToken::query()->sole())
        ->abilities->toBe(['baca-aset'])
        ->expires_at->toDateString()->toBe(today()->addMonths(6)->toDateString());
});

test('an expired token is refused, and expired tokens are pruned every day', function () {
    $expired = $this->adminIt->createToken('lama', ['baca-aset'], now()->subDay())->plainTextToken;

    $this->withToken($expired)->getJson('/api/v1/assets')->assertUnauthorized();

    Artisan::call('schedule:list');
    expect(Artisan::output())->toContain('sanctum:prune-expired');
});
