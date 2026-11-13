<?php

use App\Models\User;

test('the shared login page is shown in Indonesian', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Masuk ke akun Anda');
});

test('the old login page of each panel sends visitors to the shared login page', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with(['/admin/login', '/karyawan/login']);

test('there is no registration page', function (string $path) {
    $this->get($path)->assertNotFound();
})->with(['/register', '/admin/register', '/karyawan/register']);

test('the home address sends guests to the login page', function () {
    $this->get('/')->assertRedirect('/login');
});

test('the home address sends a signed-in user to the panel of their role', function (string $role, string $panel, string $home) {
    $this->actingAs(userWithRole($role, $panel));

    $this->get('/')->assertRedirect($home);
})->with([['admin_it', 'admin', '/admin'], ['karyawan', 'karyawan', '/karyawan']]);

test('the home address refuses a signed-in user who cannot open any panel', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/')->assertForbidden();
});

test('karyawan can open the karyawan panel but not the admin panel', function () {
    $this->actingAs(userWithRole('karyawan', 'karyawan'));

    // Panel karyawan tidak punya dashboard; halaman awalnya Aset saya.
    $this->get('/karyawan')->assertRedirect('/karyawan/aset-saya');
    $this->get('/karyawan/aset-saya')->assertOk();
    $this->get('/admin')->assertForbidden();
});

test('every admin role can open both the admin panel and the karyawan panel', function (string $role) {
    $this->actingAs(userWithRole($role));

    $this->get('/admin')->assertOk();
    $this->get('/karyawan/aset-saya')->assertOk();
})->with(['super_admin', 'admin_it', 'admin_ga']);

test('the user menu of the admin panel links to the karyawan panel', function () {
    $this->actingAs(userWithRole('admin_it'));

    $this->get('/admin')->assertSee('Panel karyawan')->assertSee(url('/karyawan'));
});

test('the user menu of the karyawan panel links to the admin panel only for admins', function (string $role, string $panel, bool $seesLink) {
    $this->actingAs(userWithRole($role, $panel));

    $response = $this->get('/karyawan/aset-saya');

    $seesLink ? $response->assertSee('Panel admin') : $response->assertDontSee('Panel admin');
})->with([['admin_it', 'admin', true], ['karyawan', 'karyawan', false]]);

test('super admin can open the admin panel and the roles page', function () {
    $this->actingAs(userWithRole('super_admin'));

    $this->get('/admin')->assertOk();
    $this->get('/admin/shield/roles')->assertOk();
});

test('admin IT cannot open the roles page', function () {
    $this->actingAs(userWithRole('admin_it'));

    $this->get('/admin/shield/roles')->assertForbidden();
});

test('an inactive user cannot open any panel', function () {
    $this->actingAs(userWithRole('super_admin', attributes: ['is_active' => false]));

    $this->get('/admin')->assertForbidden();
    $this->get('/karyawan')->assertForbidden();
});

test('a user without 2FA opens the panel directly because 2FA is optional', function (string $role, string $panel, string $home) {
    $this->actingAs(userWithRole($role, $panel, twoFactor: false));

    $this->get($home)->assertOk();
})->with([['admin_it', 'admin', '/admin'], ['karyawan', 'karyawan', '/karyawan/aset-saya']]);

test('the password reset request page is shown', function () {
    $this->get('/password-reset/request')->assertOk();
});

test('a user without a role cannot open any panel', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin')->assertForbidden();
    $this->get('/karyawan')->assertForbidden();
});
