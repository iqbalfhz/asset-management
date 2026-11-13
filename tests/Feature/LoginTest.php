<?php

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Jeffgreco13\FilamentBreezy\Pages\TwoFactorPage;
use Livewire\Features\SupportTesting\Testable;
use PragmaRX\Google2FA\Google2FA;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

// Halaman masuk bersama ada di panel auth; user dibuat lebih dulu karena factory 2FA butuh plugin Breezy di panel aktif.
function logInAs(string $login, string $password = 'Kantor2026aman'): Testable
{
    Filament::setCurrentPanel('auth');

    return livewire(Login::class)
        ->fillForm(['login' => $login, 'password' => $password])
        ->call('authenticate');
}

test('after login, a user lands on the panel of their role', function (string $role, string $panel, string $home) {
    userWithRole($role, $panel, ['username' => 'budi', 'password' => 'Kantor2026aman']);

    logInAs('budi')->assertRedirect($home);
})->with([['admin_it', 'admin', '/admin'], ['karyawan', 'karyawan', '/karyawan']]);

test('after login, a user returns to the page they first asked for', function () {
    userWithRole('karyawan', 'karyawan', ['username' => 'budi', 'password' => 'Kantor2026aman']);
    session(['url.intended' => url('/a/TCM-LPT-2026-00001')]);

    logInAs('budi')->assertRedirect('/a/TCM-LPT-2026-00001');
});

test('after login, a karyawan who first asked for an admin page lands on the karyawan panel', function () {
    userWithRole('karyawan', 'karyawan', ['username' => 'budi', 'password' => 'Kantor2026aman']);
    session(['url.intended' => url('/admin/assets')]);

    logInAs('budi')->assertRedirect('/karyawan');
});

test('an account that cannot open any panel cannot log in', function (User $user) {
    logInAs($user->username)->assertHasFormErrors(['login']);

    $this->assertGuest();
})->with([
    'inactive admin' => fn () => userWithRole('admin_it', attributes: ['username' => 'budi', 'password' => 'Kantor2026aman', 'is_active' => false]),
    'account without a role' => fn () => User::factory()->create(['username' => 'budi', 'password' => 'Kantor2026aman']),
]);

test('a user can log in with username or email', function (string $login) {
    $user = userWithRole('admin_it', attributes: [
        'username' => 'budi',
        'email' => 'budi@tangcity.com',
        'password' => 'Kantor2026aman',
    ], twoFactor: false);

    logInAs($login)->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
})->with(['budi', 'budi@tangcity.com']);

test('a wrong password is rejected', function () {
    userWithRole('admin_it', attributes: ['username' => 'budi', 'password' => 'Kantor2026aman']);

    logInAs('budi', 'Salah2026sekali')->assertHasFormErrors(['login']);

    $this->assertGuest();
});

test('after login, a user with 2FA must enter a valid code before opening the panel', function () {
    $user = userWithRole('admin_it', attributes: ['username' => 'budi', 'password' => 'Kantor2026aman']);
    $validCode = app(Google2FA::class)->getCurrentOtp($user->breezySession->two_factor_secret);
    $wrongCode = $validCode === '000000' ? '111111' : '000000';

    logInAs('budi');

    $this->get('/admin')->assertRedirectContains('two-factor');

    livewire(TwoFactorPage::class)
        ->set('data.code', $wrongCode)
        ->call('authenticate')
        ->assertHasErrors(['data.code']);

    $this->get('/admin')->assertRedirectContains('two-factor');

    livewire(TwoFactorPage::class)
        ->set('data.code', $validCode)
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->get('/admin')->assertOk();
});

test('the 2FA secret and recovery codes are stored encrypted', function () {
    $user = User::factory()->withTwoFactor()->create();
    $stored = DB::table('breezy_sessions')->where('authenticatable_id', $user->id)->first();

    expect($stored->two_factor_secret)->not->toBe($user->breezySession->two_factor_secret)
        ->and(Crypt::decryptString($stored->two_factor_secret))->toBe($user->breezySession->two_factor_secret)
        ->and($stored->two_factor_recovery_codes)->not->toContain($user->breezySession->two_factor_recovery_codes[0]);
});
