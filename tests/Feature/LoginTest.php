<?php

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Jeffgreco13\FilamentBreezy\Pages\TwoFactorPage;
use PragmaRX\Google2FA\Google2FA;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

test('a user can log in with username or email', function (string $login) {
    $user = userWithRole('admin_it', attributes: [
        'username' => 'budi',
        'email' => 'budi@tangcity.com',
        'password' => 'Kantor2026aman',
    ], twoFactor: false);

    livewire(Login::class)
        ->fillForm(['login' => $login, 'password' => 'Kantor2026aman'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $this->assertAuthenticatedAs($user);
})->with(['budi', 'budi@tangcity.com']);

test('a wrong password is rejected', function () {
    userWithRole('admin_it', attributes: ['username' => 'budi', 'password' => 'Kantor2026aman']);

    livewire(Login::class)
        ->fillForm(['login' => 'budi', 'password' => 'Salah2026sekali'])
        ->call('authenticate')
        ->assertHasFormErrors(['login']);

    $this->assertGuest();
});

test('after login, a user with 2FA must enter a valid code before opening the panel', function () {
    $user = userWithRole('admin_it', attributes: ['username' => 'budi', 'password' => 'Kantor2026aman']);
    $validCode = app(Google2FA::class)->getCurrentOtp($user->breezySession->two_factor_secret);
    $wrongCode = $validCode === '000000' ? '111111' : '000000';

    livewire(Login::class)
        ->fillForm(['login' => 'budi', 'password' => 'Kantor2026aman'])
        ->call('authenticate');

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
