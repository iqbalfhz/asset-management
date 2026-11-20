<?php

use App\Models\Setting;

test('every panel shows the footer with the running version and the IT contact', function (string $path, ?string $role, string $panel) {
    config(['app.version' => 'abc1234def5678']);
    Setting::put('kontak_it', 'it@contoh.test');

    if ($role !== null) {
        $this->actingAs(userWithRole($role, $panel));
    }

    $this->get($path)
        ->assertOk()
        ->assertSeeInOrder(['kertas-footer', config('app.name'), 'versi abc1234', '© '.now()->year, 'Ada kendala? Hubungi Tim IT: it@contoh.test'], false)
        ->assertDontSee('abc1234def');
})->with([
    'login' => ['/login', null, 'auth'],
    'admin' => ['/admin', 'admin_it', 'admin'],
    'karyawan' => ['/karyawan/aset-saya', 'karyawan', 'karyawan'],
]);

test('the footer leaves out the version and IT contact when they are not set', function () {
    config(['app.version' => null]);

    $this->get('/login')
        ->assertOk()
        ->assertSee('kertas-footer', false)
        ->assertDontSee('· versi')
        ->assertDontSee('Hubungi Tim IT');
});

test('the IT contact on the public login page is shown as text, not HTML', function () {
    Setting::put('kontak_it', '<b>it</b>');

    $this->get('/login')
        ->assertOk()
        ->assertSee('&lt;b&gt;it&lt;/b&gt;', false)
        ->assertDontSee('<b>it</b>', false);
});
