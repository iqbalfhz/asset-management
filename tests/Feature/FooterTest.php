<?php

use App\Models\Setting;
use Illuminate\Support\Str;

test('every panel shows the footer with the release number, running commit and IT contact', function (string $path, ?string $role, string $panel) {
    config(['app.version' => '1.2.3', 'app.commit' => 'abc1234def5678']);
    Setting::put('kontak_it', 'it@contoh.test');

    if ($role !== null) {
        $this->actingAs(userWithRole($role, $panel));
    }

    $this->get($path)
        ->assertOk()
        ->assertSeeInOrder([
            'kertas-footer', config('app.name'), 'versi 1.2.3', '(abc1234)', '© '.now()->year, 'Ada kendala? Hubungi Tim IT: it@contoh.test',
            'Dikembangkan oleh Iqbal Fahrozi', 'href="https://github.com/iqbalfhz" target="_blank" rel="noopener noreferrer"',
        ], false)
        ->assertDontSee('abc1234def');
})->with([
    'login' => ['/login', null, 'auth'],
    'admin' => ['/admin', 'admin_it', 'admin'],
    'karyawan' => ['/karyawan/aset-saya', 'karyawan', 'karyawan'],
]);

test('the footer leaves out the commit and IT contact when they are not set', function () {
    config(['app.version' => '1.2.3', 'app.commit' => null]);

    $footer = Str::between($this->get('/login')->assertOk()->getContent(), '<footer class="kertas-footer">', '</footer>');

    expect($footer)->toContain('versi 1.2.3')
        ->not->toContain('(')
        ->not->toContain('Hubungi Tim IT');
});

test('the IT contact on the public login page is shown as text, not HTML', function () {
    Setting::put('kontak_it', '<b>it</b>');

    $this->get('/login')
        ->assertOk()
        ->assertSee('&lt;b&gt;it&lt;/b&gt;', false)
        ->assertDontSee('<b>it</b>', false);
});
