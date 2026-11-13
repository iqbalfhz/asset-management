<?php

use App\Filament\Pages\Auth\RequestPasswordReset as AppRequestPasswordReset;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Jeffgreco13\FilamentBreezy\Livewire\UpdatePassword;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

test('changing the password on the profile page rejects a weak one', function (string $password) {
    $this->actingAs(userWithRole('admin_it', attributes: ['email' => null, 'password' => 'Kantor2026aman']));

    livewire(UpdatePassword::class)
        ->fillForm(['current_password' => 'Kantor2026aman', 'new_password' => $password, 'new_password_confirmation' => $password])
        ->call('submit')
        ->assertHasFormErrors(['new_password']);
})->with(['12345678', 'passwordtanpaangka']);

test('a user without email can change to a strong password on the profile page', function () {
    $user = userWithRole('admin_it', attributes: ['email' => null, 'password' => 'Kantor2026aman']);
    $this->actingAs($user);

    livewire(UpdatePassword::class)
        ->fillForm(['current_password' => 'Kantor2026aman', 'new_password' => 'Gudang2026baru', 'new_password_confirmation' => 'Gudang2026baru'])
        ->call('submit')
        ->assertHasNoFormErrors();

    expect(Hash::check('Gudang2026baru', $user->refresh()->password))->toBeTrue();
});

test('resetting the password rejects a weak one', function () {
    $user = User::factory()->create(['email' => 'budi@tangcity.com']);

    livewire(ResetPassword::class, ['email' => $user->email, 'token' => Password::broker()->createToken($user)])
        ->fillForm(['password' => '12345678', 'passwordConfirmation' => '12345678'])
        ->call('resetPassword')
        ->assertHasFormErrors(['password']);
});

test('a reset link is sent to an account with email that can open the panel', function () {
    Notification::fake();
    $user = userWithRole('admin_it', attributes: ['email' => 'budi@tangcity.com']);

    livewire(RequestPasswordReset::class)
        ->fillForm(['email' => 'budi@tangcity.com'])
        ->call('request')
        ->assertHasNoFormErrors();

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

test('no reset link is sent for a panel the account cannot open', function () {
    Notification::fake();
    $user = userWithRole('karyawan', 'karyawan', ['email' => 'budi@tangcity.com']);

    livewire(RequestPasswordReset::class)
        ->fillForm(['email' => 'budi@tangcity.com'])
        ->call('request');

    Notification::assertNotSentTo($user, ResetPasswordNotification::class);
});

test('the reset form gives the same answer with the waiting notice for every email', function (string $email) {
    Notification::fake();
    userWithRole('admin_it', attributes: ['email' => 'ani@tangcity.com']);
    $user = userWithRole('admin_it', attributes: ['email' => 'budi@tangcity.com']);
    Password::broker()->createToken($user);

    livewire(AppRequestPasswordReset::class)
        ->fillForm(['email' => $email])
        ->call('request')
        ->assertNotified(__('Cek email Anda'))
        ->assertFormSet(['email' => null]);
})->with([
    'registered email' => 'ani@tangcity.com',
    'unknown email' => 'tidakada@tangcity.com',
    'email that just got a link' => 'budi@tangcity.com',
]);
