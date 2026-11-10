<?php

use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

test('a user can log in with username or email', function (string $login) {
    $user = userWithRole('admin_it', attributes: [
        'username' => 'budi',
        'email' => 'budi@tangcity.com',
        'password' => 'Kantor2026aman',
        'app_authentication_secret' => null,
    ]);

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

test('a user with 2FA is only logged in with a valid code', function () {
    $user = userWithRole('admin_it', attributes: ['username' => 'budi', 'password' => 'Kantor2026aman']);
    $validCode = AppAuthentication::make()->getCurrentCode($user);
    $wrongCode = $validCode === '000000' ? '111111' : '000000';

    $login = livewire(Login::class)
        ->fillForm(['login' => 'budi', 'password' => 'Kantor2026aman'])
        ->call('authenticate');

    $this->assertGuest();

    $login->set('data.multiFactor.app.code', $wrongCode)
        ->call('authenticate')
        ->assertHasErrors(['data.multiFactor.app.code']);

    $this->assertGuest();

    $login->set('data.multiFactor.app.code', $validCode)
        ->call('authenticate');

    $this->assertAuthenticatedAs($user);
});

test('the 2FA secret is stored encrypted', function () {
    $user = User::factory()->withAppAuthentication()->create();
    $stored = DB::table('users')->where('id', $user->id)->value('app_authentication_secret');

    expect($stored)->not->toBe($user->getAppAuthenticationSecret())
        ->and(Crypt::decryptString($stored))->toBe($user->getAppAuthenticationSecret());
});
