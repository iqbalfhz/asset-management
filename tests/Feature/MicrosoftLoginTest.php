<?php

use App\Enums\LoginResult;
use App\Models\LoginLog;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as MicrosoftAccount;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
    config(['services.microsoft' => [
        'client_id' => 'aplikasi-aset',
        'client_secret' => 'rahasia',
        'tenant' => 'tenant-kantor',
        'redirect' => '/login/microsoft/callback',
    ]]);
});

function signInWithMicrosoft(string $id, string $email): void
{
    Socialite::fake('microsoft', MicrosoftAccount::fake(['id' => $id, 'email' => $email, 'name' => 'Budi']));
}

test('the login page offers Microsoft only when it is set up', function () {
    $this->get('/login')->assertOk()->assertSee('Masuk dengan akun Microsoft kantor');

    config(['services.microsoft.tenant' => null]);

    $this->get('/login')->assertOk()->assertDontSee('Masuk dengan akun Microsoft kantor');
    $this->get('/login/microsoft')->assertNotFound();
});

test('the button sends the user to Microsoft', function () {
    Socialite::fake('microsoft');

    $this->get('/login/microsoft')->assertRedirect();
});

test('an office account signs in to the user with the same email, and stays linked by its Microsoft ID', function () {
    $budi = userWithRole('admin_it', attributes: ['email' => 'Budi@tangcity.com'], twoFactor: false);
    signInWithMicrosoft('oid-budi', 'budi@TANGCITY.com');

    $this->get('/login/microsoft/callback')->assertRedirect('/admin');

    $this->assertAuthenticatedAs($budi);
    expect($budi->refresh()->microsoft_id)->toBe('oid-budi')
        ->and(LoginLog::query()->sole()->hasil)->toBe(LoginResult::Berhasil);

    auth()->logout();
    $budi->update(['email' => 'budi.santoso@tangcity.com']);

    $this->get('/login/microsoft/callback')->assertRedirect('/admin');
    $this->assertAuthenticatedAs($budi);
});

test('an account that is unknown, inactive or linked to another Microsoft ID cannot sign in', function (array $attributes) {
    userWithRole('admin_it', attributes: $attributes + ['email' => 'budi@tangcity.com']);
    signInWithMicrosoft('oid-budi', 'budi@tangcity.com');

    $this->get('/login/microsoft/callback')->assertRedirect('/login');

    $this->assertGuest();
    expect(LoginLog::query()->sole()->hasil)->toBe(LoginResult::Gagal);
})->with([
    'unknown email' => [['email' => 'ani@tangcity.com']],
    'inactive' => [['is_active' => false]],
    'linked to another ID' => [['microsoft_id' => 'oid-lama']],
]);

test('2FA is still asked after signing in with Microsoft', function () {
    userWithRole('admin_it', attributes: ['email' => 'budi@tangcity.com']);
    signInWithMicrosoft('oid-budi', 'budi@tangcity.com');

    $this->get('/login/microsoft/callback');

    $this->get('/admin')->assertRedirectContains('two-factor');
});

test('a cancelled sign in returns to the login page', function () {
    $this->get('/login/microsoft/callback?error=access_denied')->assertRedirect('/login');

    $this->assertGuest();
});
