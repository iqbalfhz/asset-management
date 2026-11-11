<?php

use App\Models\User;

test('the login page of each panel is shown in Indonesian', function (string $path) {
    $this->get($path)
        ->assertOk()
        ->assertSee('Masuk ke akun Anda');
})->with(['/admin/login', '/karyawan/login']);

test('panels have no registration page', function (string $path) {
    $this->get($path)->assertNotFound();
})->with(['/admin/register', '/karyawan/register']);

test('karyawan can open the karyawan panel but not the admin panel', function () {
    $this->actingAs(userWithRole('karyawan', 'karyawan'));

    $this->get('/karyawan')->assertOk();
    $this->get('/admin')->assertForbidden();
});

test('admin IT can open the admin panel but not the karyawan panel', function () {
    $this->actingAs(userWithRole('admin_it'));

    $this->get('/admin')->assertOk();
    $this->get('/karyawan')->assertForbidden();
});

test('super admin can open the admin panel and the roles page', function () {
    $this->actingAs(userWithRole('super_admin'));

    $this->get('/admin')->assertOk();
    $this->get('/admin/shield/roles')->assertOk();
});

test('admin IT cannot open the roles page', function () {
    $this->actingAs(userWithRole('admin_it'));

    $this->get('/admin/shield/roles')->assertForbidden();
});

test('an inactive user cannot open the panel of their role', function () {
    $this->actingAs(userWithRole('super_admin', attributes: ['is_active' => false]));

    $this->get('/admin')->assertForbidden();
});

test('a user without 2FA is sent to the profile page to set it up first', function (string $role, string $panel) {
    $this->actingAs(userWithRole($role, $panel, twoFactor: false));

    $this->get("/{$panel}")->assertRedirect("/{$panel}/profil");
    $this->get("/{$panel}/profil")->assertOk();
})->with([['admin_it', 'admin'], ['karyawan', 'karyawan']]);

test('the password reset request page of each panel is shown', function (string $path) {
    $this->get($path)->assertOk();
})->with(['/admin/password-reset/request', '/karyawan/password-reset/request']);

test('a user without a role cannot open any panel', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin')->assertForbidden();
    $this->get('/karyawan')->assertForbidden();
});
