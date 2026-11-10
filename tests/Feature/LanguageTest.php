<?php

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

test('password validation messages are in Indonesian', function () {
    $errors = Validator::make(['password' => '12345678'], ['password' => Password::defaults()])
        ->setAttributeNames(['password' => 'kata sandi'])
        ->errors()
        ->all();

    expect($errors)->toBe([
        'Kolom kata sandi minimal 12 karakter.',
        'Kolom kata sandi harus berisi paling sedikit satu huruf.',
    ]);
});

test('the 2FA code label has no typo', function () {
    expect(__('filament-panels::auth/multi-factor/app/provider.login_form.code.label'))
        ->toBe('Masukkan kode 6 digit dari aplikasi authenticator')
        ->and(__('filament-panels::auth/multi-factor/app/provider.login_form.code.validation_attribute'))
        ->toBe('kode');
});

test('the password reset link message is in Indonesian', function () {
    expect(__('passwords.sent'))->toBe('Link atur ulang kata sandi sudah kami kirim ke email Anda.');
});
