<?php

use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('local');
});

test('only a super admin opens the backup page', function () {
    $this->actingAs(userWithRole('admin_it'))->get(Backups::getUrl(panel: 'admin'))->assertForbidden();
    $this->actingAs(userWithRole('super_admin'))->get(Backups::getUrl(panel: 'admin'))->assertOk();
});

test('only a super admin may download backups, and nobody deletes them from the panel', function () {
    $superAdmin = userWithRole('super_admin');
    $adminIt = userWithRole('admin_it');

    expect(Gate::forUser($superAdmin)->allows('download-backup'))->toBeTrue()
        ->and(Gate::forUser($adminIt)->allows('download-backup'))->toBeFalse()
        ->and(Gate::forUser($superAdmin)->allows('delete-backup'))->toBeFalse();
});
