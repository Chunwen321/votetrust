<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            // PIC: Chew Zhexuan — User module factory attributes.
            'name' => $name,
            'full_name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'date_of_birth' => '2000-01-01',
            'nationality_country_code' => 'MY',
            'account_status' => 'ACTIVE',
            'is_verified' => true,
            'verified_at' => now(),
            'verification_status' => 'APPROVED',
            'failed_login_attempts' => 0,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the member still needs to complete profile eKYC verification.
     */
    public function unverifiedProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => false,
            'verified_at' => null,
            'verification_reference' => null,
            'verification_provider' => null,
            'verification_session_id' => null,
            'verification_status' => 'NOT_STARTED',
            'verification_requested_at' => null,
            'verification_consent_at' => null,
            'verification_last_event_id' => null,
            'verification_failure_reason' => null,
        ]);
    }
}
