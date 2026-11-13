<?php

use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Models\User;
use App\Notifications\PasswordResetCode;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    Notification::fake();
});

function requestResetCode(string $email): Testable
{
    return livewire(RequestPasswordReset::class)
        ->fillForm(['email' => $email])
        ->call('request');
}

function lastResetCode(User $user): string
{
    return Notification::sent($user, PasswordResetCode::class)->last()->code;
}

function submitResetCode(Testable $page, string $code, string $password = 'Gudang2026baru'): Testable
{
    return $page
        ->fillForm(['code' => $code, 'password' => $password, 'passwordConfirmation' => $password])
        ->call('request');
}

test('a reset code is sent after the response to an account that can open the panel', function (string $role, string $panel) {
    Filament::setCurrentPanel($panel);
    $user = userWithRole($role, $panel, ['email' => 'budi@tangcity.com']);

    requestResetCode('budi@tangcity.com')->assertHasNoFormErrors();

    Notification::assertSentTo($user, PasswordResetCode::class, function (PasswordResetCode $notification) use ($user): bool {
        return $notification->connection === 'deferred'
            && preg_match('/^\d{6}$/', $notification->code) === 1
            && in_array('**'.$notification->code.'**', $notification->toMail($user)->introLines, true);
    });
})->with([
    'admin panel' => ['admin_it', 'admin'],
    'karyawan panel' => ['karyawan', 'karyawan'],
]);

test('no reset code is sent to an account that cannot open the panel', function (string $role, string $panel, array $attributes) {
    $user = userWithRole($role, $panel, ['email' => 'budi@tangcity.com'] + $attributes);

    requestResetCode('budi@tangcity.com');

    Notification::assertNotSentTo($user, PasswordResetCode::class);
})->with([
    'karyawan on the admin panel' => ['karyawan', 'karyawan', []],
    'inactive admin' => ['admin_it', 'admin', ['is_active' => false]],
]);

test('every email gets the same code step', function (string $email) {
    userWithRole('admin_it', attributes: ['email' => 'budi@tangcity.com']);

    requestResetCode($email)
        ->assertHasNoFormErrors()
        ->assertSet('email', $email)
        ->assertSee('kode 6 digit sudah dikirim');
})->with([
    'registered email' => 'budi@tangcity.com',
    'unknown email' => 'tidakada@tangcity.com',
]);

test('the right code sets the new password and cannot be used again', function () {
    $user = userWithRole('admin_it', attributes: ['email' => 'budi@tangcity.com', 'password' => 'Kantor2026lama']);
    $page = requestResetCode('budi@tangcity.com');
    $code = lastResetCode($user);

    submitResetCode($page, $code)
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getLoginUrl());

    expect(Hash::check('Gudang2026baru', $user->refresh()->password))->toBeTrue();

    submitResetCode(requestResetCode('budi@tangcity.com'), $code, 'Lemari2026baru')
        ->assertHasFormErrors(['code']);

    expect(Hash::check('Gudang2026baru', $user->refresh()->password))->toBeTrue();
});

test('the new password must follow the password policy', function () {
    $user = userWithRole('admin_it', attributes: ['email' => 'budi@tangcity.com']);
    $page = requestResetCode('budi@tangcity.com');

    submitResetCode($page, lastResetCode($user), '12345678')
        ->assertHasFormErrors(['password']);
});

test('five wrong codes burn the code', function () {
    $user = userWithRole('admin_it', attributes: ['email' => 'budi@tangcity.com', 'password' => 'Kantor2026lama']);
    $page = requestResetCode('budi@tangcity.com');
    $code = lastResetCode($user);
    $wrongCode = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $attempt) {
        submitResetCode($page, $wrongCode)->assertHasFormErrors(['code']);
    }

    submitResetCode($page, $code)->assertHasFormErrors(['code']);

    $this->travel(61)->minutes();

    submitResetCode($page, $code)->assertHasFormErrors(['code']);

    expect(Hash::check('Kantor2026lama', $user->refresh()->password))->toBeTrue();
});

test('an expired code is rejected', function () {
    $user = userWithRole('admin_it', attributes: ['email' => 'budi@tangcity.com', 'password' => 'Kantor2026lama']);
    $page = requestResetCode('budi@tangcity.com');
    $code = lastResetCode($user);

    $this->travel(RequestPasswordReset::CODE_MINUTES + 1)->minutes();

    submitResetCode($page, $code)->assertHasFormErrors(['code']);

    expect(Hash::check('Kantor2026lama', $user->refresh()->password))->toBeTrue();
});

test('a new code waits 60 seconds and replaces the old one', function () {
    $user = userWithRole('admin_it', attributes: ['email' => 'budi@tangcity.com']);
    $page = requestResetCode('budi@tangcity.com');
    $oldCode = lastResetCode($user);

    $page->callAction(TestAction::make('resendCode')->schemaComponent('code'));
    Notification::assertSentToTimes($user, PasswordResetCode::class, 1);

    $this->travel(61)->seconds();

    $page->callAction(TestAction::make('resendCode')->schemaComponent('code'));
    Notification::assertSentToTimes($user, PasswordResetCode::class, 2);
    $newCode = lastResetCode($user);

    // Kode baru acak; kalau kebetulan sama dengan kode lama, pengecekan kode lama dilewati.
    if ($newCode !== $oldCode) {
        submitResetCode($page, $oldCode)->assertHasFormErrors(['code']);
    }

    submitResetCode($page, $newCode)->assertHasNoFormErrors();
});
