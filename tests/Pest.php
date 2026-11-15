<?php

use App\Models\Role;
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
