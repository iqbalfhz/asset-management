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

function verifyResetCode(Testable $page, string $code): Testable
{
    return $page
        ->fillForm(['code' => $code])
        ->call('request');
}

function setNewPassword(Testable $page, string $password = 'Gudang2026baru'): Testable
{
    return $page
        ->fillForm(['password' => $password, 'passwordConfirmation' => $password])
        ->call('request');
}

test('a reset code is sent after the response to an account that can open the panel', function (string $role, string $panel) {
    Filament::setCurrentPanel($panel);
    $user = userWithRole($role, $panel, ['email' => 'budi@tangcity.com']);

    requestResetCode('budi@tangcity.com')->assertHasNoFormErrors();

    Notification::assertSentTo($user, PasswordResetCode::class, function (PasswordResetCode $notification) use ($user): bool {
        $html = (string) $notification->toMail($user)->render();

        return $notification->connection === 'deferred'
            && preg_match('/^\d{6}$/', $notification->code) === 1
            && str_contains($html, '>'.$notification->code.'<')
            && str_contains($html, e($user->name));
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
        ->assertSee('kode 6 digit sudah dikirim')
        ->assertFormFieldVisible('code')
        ->assertFormFieldHidden('password');
})->with([
    'registered email' => 'budi@tangcity.com',
    'unknown email' => 'tidakada@tangcity.com',
]);

test('the new password fields appear only after the right code', function () {
    $user = userWithRole('admin_it', attributes: ['email' => 'budi@tangcity.com']);
    $page = requestResetCode('budi@tangcity.com');
    $code = lastResetCode($user);

    verifyResetCode($page, $code === '000000' ? '111111' : '000000')
        ->assertHasFormErrors(['code'])
        ->assertFormFieldHidden('password');

    verifyResetCode($page, $code)
        ->assertHasNoFormErrors()
        ->assertFormFieldHidden('code')
        ->assertFormFieldVisible('password')
        ->assertFormFieldVisible('passwordConfirmation');
});

test('the right code sets the new password and cannot be used again', function () {
    $user = userWithRole('admin_it', attributes: ['email' => 'budi@tangcity.com', 'password' => 'Kantor2026lama']);
    $page = requestResetCode('budi@tangcity.com');
    $code = lastResetCode($user);

    setNewPassword(verifyResetCode($page, $code))
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getLoginUrl());

    expect(Hash::check('Gudang2026baru', $user->refresh()->password))->toBeTrue();

    verifyResetCode(requestResetCode('budi@tangcity.com'), $code)
        ->assertHasFormErrors(['code']);
});

test('the new password must follow the password policy', function () {
    $user = userWithRole('admin_it', attributes: ['email' => 'budi@tangcity.com']);
    $page = requestResetCode('budi@tangcity.com');

    setNewPassword(verifyResetCode($page, lastResetCode($user)), '12345678')
        ->assertHasFormErrors(['password']);
});

test('five wrong codes burn the code', function () {
    $user = userWithRole('admin_it', attributes: ['email' => 'budi@tangcity.com', 'password' => 'Kantor2026lama']);
    $page = requestResetCode('budi@tangcity.com');
    $code = lastResetCode($user);
    $wrongCode = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $attempt) {
        verifyResetCode($page, $wrongCode)->assertHasFormErrors(['code']);
    }

    verifyResetCode($page, $code)->assertHasFormErrors(['code']);

    $this->travel(61)->minutes();

    verifyResetCode($page, $code)
        ->assertHasFormErrors(['code'])
        ->assertFormFieldHidden('password');

    expect(Hash::check('Kantor2026lama', $user->refresh()->password))->toBeTrue();
});

test('an expired code is rejected', function () {
    $user = userWithRole('admin_it', attributes: ['email' => 'budi@tangcity.com']);
    $page = requestResetCode('budi@tangcity.com');
    $code = lastResetCode($user);

    $this->travel(RequestPasswordReset::CODE_MINUTES + 1)->minutes();

    verifyResetCode($page, $code)
        ->assertHasFormErrors(['code'])
        ->assertFormFieldHidden('password');
});

test('the time to set a new password runs out', function () {
    $user = userWithRole('admin_it', attributes: ['email' => 'budi@tangcity.com', 'password' => 'Kantor2026lama']);
    $page = verifyResetCode(requestResetCode('budi@tangcity.com'), lastResetCode($user));

    $this->travel(RequestPasswordReset::CODE_MINUTES + 1)->minutes();

    setNewPassword($page)
        ->assertNotified(__('Waktu atur ulang habis. Minta kode baru.'))
        ->assertFormFieldVisible('code');

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
        verifyResetCode($page, $oldCode)->assertHasFormErrors(['code']);
    }

    verifyResetCode($page, $newCode)->assertHasNoFormErrors();
});
