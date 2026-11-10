<?php

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->companyA = Company::factory()->create();
    $this->companyB = Company::factory()->create();

    User::factory()->create(['company_id' => $this->companyB->id]);
});

test('with separation on, a user only sees users of their own company', function () {
    config(['app.company_separation' => true]);
    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->companyA->id]));

    expect(User::where('company_id', $this->companyA->id)->exists())->toBeTrue()
        ->and(User::where('company_id', $this->companyB->id)->exists())->toBeFalse();
});

test('with separation on, super admin sees users of every company', function () {
    config(['app.company_separation' => true]);
    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->companyA->id]));

    expect(User::where('company_id', $this->companyB->id)->exists())->toBeTrue();
});

test('with separation on, a role with view_all_companies sees users of every company', function () {
    config(['app.company_separation' => true]);
    $user = userWithRole('auditor', attributes: ['company_id' => $this->companyA->id]);
    Role::findByName('auditor')->givePermissionTo(Permission::create(['name' => 'view_all_companies']));
    $this->actingAs($user);

    expect(User::where('company_id', $this->companyB->id)->exists())->toBeTrue();
});

test('with separation off, users of every company are visible', function () {
    config(['app.company_separation' => false]);
    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->companyA->id]));

    expect(User::where('company_id', $this->companyB->id)->exists())->toBeTrue();
});
