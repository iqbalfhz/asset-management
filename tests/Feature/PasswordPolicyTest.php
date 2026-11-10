<?php

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

test('changing the password rejects a weak one', function (string $password) {
    $this->actingAs(userWithRole('admin_it', attributes: ['email' => null, 'password' => 'Kantor2026aman']));

    livewire(EditProfile::class)
        ->fillForm(['password' => $password, 'passwordConfirmation' => $password, 'currentPassword' => 'Kantor2026aman'])
        ->call('save')
        ->assertHasFormErrors(['password']);
})->with(['12345678', 'passwordtanpaangka']);

test('a user without email can change to a strong password', function () {
    $user = userWithRole('admin_it', attributes: ['email' => null, 'password' => 'Kantor2026aman']);
    $this->actingAs($user);

    livewire(EditProfile::class)
        ->fillForm(['password' => 'Gudang2026baru', 'passwordConfirmation' => 'Gudang2026baru', 'currentPassword' => 'Kantor2026aman'])
        ->call('save')
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
