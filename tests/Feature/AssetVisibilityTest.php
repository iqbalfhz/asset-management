<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\Company;
use App\Models\Role;
use App\Models\Setting;
use Database\Seeders\RoleSeeder;
use Database\Seeders\StatusSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed([RoleSeeder::class, StatusSeeder::class]);
    $this->company = Company::factory()->create();

    $this->laptop = Category::factory()->create(['role_id' => Role::findByName('admin_it')->id]);
    $this->kursi = Category::factory()->create(['role_id' => Role::findByName('admin_ga')->id]);

    $this->laptopAsset = Asset::factory()->create(['company_id' => $this->company->id, 'category_id' => $this->laptop->id]);
    $this->kursiAsset = Asset::factory()->create(['company_id' => $this->company->id, 'category_id' => $this->kursi->id]);
});

test('an admin only sees assets of the categories their role manages', function () {
    $this->actingAs(userWithRole('admin_ga', attributes: ['company_id' => $this->company->id]));

    expect(Asset::pluck('id')->all())->toBe([$this->kursiAsset->id]);

    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->company->id]));

    expect(Asset::pluck('id')->all())->toBe([$this->laptopAsset->id]);
});

test('super admin and roles with view_all_assets see every category', function () {
    $this->actingAs(userWithRole('super_admin', attributes: ['company_id' => $this->company->id]));
    expect(Asset::count())->toBe(2);

    $auditor = userWithRole('auditor', attributes: ['company_id' => $this->company->id]);
    Role::findByName('auditor')->givePermissionTo(Permission::findOrCreate('view_all_assets'));
    $this->actingAs($auditor);

    expect(Asset::count())->toBe(2);
});

test('with separation on, assets of another company stay hidden', function () {
    $otherCompany = Company::factory()->create();
    Asset::factory()->create(['company_id' => $otherCompany->id, 'category_id' => $this->laptop->id]);
    Setting::put('company_separation', true);

    $this->actingAs(userWithRole('admin_it', attributes: ['company_id' => $this->company->id]));

    expect(Asset::pluck('id')->all())->toBe([$this->laptopAsset->id]);
});
