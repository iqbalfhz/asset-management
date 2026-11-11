<?php

test('the queue and the nightly backup run from the scheduler, since the server has no worker container', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('queue:work --stop-when-empty')
        ->expectsOutputToContain('backup:run')
        ->assertSuccessful();
});
