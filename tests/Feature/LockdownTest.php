<?php

use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('lockdown logs everyone out and revokes every API token', function () {
    $admin = userWithRole('admin_it', twoFactor: false);
    DB::table('sessions')->insert(['id' => 'sess1', 'payload' => 'x', 'last_activity' => now()->timestamp]);
    $admin->createToken('api', ['baca-aset']);

    $this->artisan('lockdown --force')->assertSuccessful();

    expect(DB::table('sessions')->count())->toBe(0)
        ->and(PersonalAccessToken::query()->count())->toBe(0);
});

test('lockdown --accounts freezes non-super-admins but never the super admin or already-inactive users', function () {
    $super = userWithRole('super_admin', twoFactor: false);
    $admin = userWithRole('admin_ga', twoFactor: false);
    $karyawan = userWithRole('karyawan', 'karyawan', twoFactor: false);
    $resigned = userWithRole('karyawan', 'karyawan', ['is_active' => false], twoFactor: false);

    $this->artisan('lockdown --accounts --force')->assertSuccessful();

    expect($super->refresh()->is_active)->toBeTrue()
        ->and($admin->refresh()->is_active)->toBeFalse()
        ->and($karyawan->refresh()->is_active)->toBeFalse()
        ->and($resigned->refresh()->is_active)->toBeFalse();

    // Lift hanya mengaktifkan yang dikunci lockdown, bukan yang memang sudah nonaktif sebelumnya.
    $this->artisan('lockdown --lift')->assertSuccessful();

    expect($admin->refresh()->is_active)->toBeTrue()
        ->and($karyawan->refresh()->is_active)->toBeTrue()
        ->and($resigned->refresh()->is_active)->toBeFalse();
});

test('lift without a prior lockdown changes nothing', function () {
    $resigned = userWithRole('karyawan', 'karyawan', ['is_active' => false], twoFactor: false);

    $this->artisan('lockdown --lift')->assertSuccessful();

    expect($resigned->refresh()->is_active)->toBeFalse();
});

test('a frozen non-super-admin cannot access its panel while the super admin still can', function () {
    $admin = userWithRole('admin_ga', twoFactor: false);
    $super = userWithRole('super_admin', twoFactor: false);

    $this->artisan('lockdown --accounts --force')->assertSuccessful();

    $panel = Filament\Facades\Filament::getPanel('admin');
    expect($admin->refresh()->canAccessPanel($panel))->toBeFalse()
        ->and($super->refresh()->canAccessPanel($panel))->toBeTrue();
});
