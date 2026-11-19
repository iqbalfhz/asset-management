<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user has set up and confirmed an authenticator app (Breezy 2FA).
     */
    public function withTwoFactor(): static
    {
        return $this->afterCreating(function (User $user): void {
            $user->breezySessions()->create([
                'two_factor_secret' => app(Google2FA::class)->generateSecretKey(),
                'two_factor_recovery_codes' => $user->generateRecoveryCodes(),
                'two_factor_confirmed_at' => now(),
            ]);
        });
    }
}
