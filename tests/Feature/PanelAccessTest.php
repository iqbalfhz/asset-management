<?php

test('the login page of each panel is shown in Indonesian', function (string $path) {
    $this->get($path)
        ->assertOk()
        ->assertSee('Masuk ke akun Anda');
})->with(['/admin/login', '/karyawan/login']);

test('panels have no registration page', function (string $path) {
    $this->get($path)->assertNotFound();
})->with(['/admin/register', '/karyawan/register']);
