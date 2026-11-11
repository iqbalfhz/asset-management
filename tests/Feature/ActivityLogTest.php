<?php

use App\Models\Setting;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

test('a master data change is logged with old and new values and who made it', function () {
    $supplier = Supplier::factory()->create(['nama' => 'PT Lama']);
    $admin = userWithRole('admin_it');
    $this->actingAs($admin);

    $supplier->update(['nama' => 'PT Baru']);

    $activity = Activity::query()->where('subject_type', $supplier->getMorphClass())->where('event', 'updated')->sole();

    expect($activity->attribute_changes['old'])->toBe(['nama' => 'PT Lama'])
        ->and($activity->attribute_changes['attributes'])->toBe(['nama' => 'PT Baru'])
        ->and($activity->causer->is($admin))->toBeTrue();
});

test('saving without changes adds no log', function () {
    $supplier = Supplier::factory()->create();

    $supplier->save();

    expect(Activity::query()->where('subject_type', $supplier->getMorphClass())->where('event', 'updated')->count())->toBe(0);
});

test('a password change is not written to the log', function () {
    $user = User::factory()->create();

    $user->update(['password' => 'Gudang2026baru', 'name' => 'Nama Baru']);

    $activity = Activity::query()->where('subject_type', $user->getMorphClass())->where('event', 'updated')->sole();

    expect($activity->attribute_changes['attributes'])->toHaveKey('name')
        ->and($activity->attribute_changes['attributes'])->not->toHaveKey('password')
        ->and($activity->attribute_changes['old'])->not->toHaveKey('password');
});

test('turning company separation on is logged', function () {
    Setting::put('company_separation', true);

    expect(Activity::query()->where('subject_type', (new Setting)->getMorphClass())->count())->toBe(1);
});
