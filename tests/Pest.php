<?php

use App\Models\Asset;
use App\Models\Assignment;
use App\Models\Building;
use App\Models\Category;
use App\Models\Company;
use App\Models\Floor;
use App\Models\Role;
use App\Models\Room;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestView;
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
 * A room in a new building and floor of the given company.
 */
function roomOf(Company $company): Room
{
    $floor = Floor::factory()->create(['building_id' => Building::factory()->create(['company_id' => $company->id])->id]);

    return Room::factory()->create(['floor_id' => $floor->id]);
}

/**
 * The BAST template rendered for the given row, without the PDF step.
 */
function renderBast(Assignment $assignment, ?string $kop = null): TestView
{
    return test()->view('pdf.bast', [
        'first' => $assignment,
        'rows' => Assignment::with('asset')->where('no_bast', $assignment->no_bast)->get(),
        'company' => $assignment->asset->company,
        'kop' => $kop,
        'logo' => null,
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
