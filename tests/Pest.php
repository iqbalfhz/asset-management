<?php

use App\Models\Asset;
use App\Models\Category;
use App\Models\Company;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Create a user holding one role that belongs to the given panel, with 2FA set up unless told otherwise.
 *
 * @param  array<string, mixed>  $attributes
 */
function userWithRole(string $role, string $panel = 'admin', array $attributes = [], bool $twoFactor = true): User
{
    $factory = User::factory();
    $user = ($twoFactor ? $factory->withTwoFactor() : $factory)->create($attributes);
    $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web'], ['panel' => $panel]));

    return $user;
}

/**
 * An asset that can be handed over right away.
 *
 * @param  array<string, mixed>  $attributes
 */
function availableAsset(Company $company, Category $category, array $attributes = []): Asset
{
    return Asset::factory()->create($attributes + [
        'company_id' => $company->id,
        'category_id' => $category->id,
        'status_id' => Status::named(Status::TERSEDIA)->id,
    ]);
}

/**
 * A PNG data URL like the one the signature pad sends.
 */
function signatureDataUrl(): string
{
    $image = imagecreatetruecolor(300, 100);
    imageline($image, 20, 70, 280, 30, imagecolorallocate($image, 255, 255, 255));
    ob_start();
    imagepng($image);

    return 'data:image/png;base64,'.base64_encode(ob_get_clean());
}
