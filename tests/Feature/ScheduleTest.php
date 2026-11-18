<?php

use Illuminate\Support\Facades\Artisan;

test('the queue and the nightly backup run from the scheduler, since the server has no worker container', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('queue:work --stop-when-empty')
        ->expectsOutputToContain('backup:run')
        ->assertSuccessful();
});

test('the email digest check runs every morning at seven', function () {
    Artisan::call('schedule:list');

    expect(Artisan::output())->toMatch('/0\s+7\s+\*\s+\*\s+\*\s+php artisan notifikasi:ringkasan/');
});
