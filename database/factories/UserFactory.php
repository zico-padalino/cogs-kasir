<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Cogs,
            'modules' => [UserRole::Cogs->value],
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function cogs(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Cogs,
            'modules' => [UserRole::Cogs->value],
        ]);
    }

    public function kasir(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Kasir,
            'modules' => [UserRole::Kasir->value],
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Admin,
            'modules' => [UserRole::Admin->value],
        ]);
    }

    public function withModules(array $modules): static
    {
        $values = array_map(
            fn ($m) => $m instanceof UserRole ? $m->value : (string) $m,
            $modules,
        );

        return $this->state(fn () => [
            'role' => UserRole::from($values[0]),
            'modules' => $values,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }
}
