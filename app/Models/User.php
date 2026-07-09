<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'role', 'modules', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'modules' => 'array',
        ];
    }

    /** @return list<UserRole> */
    public function accessibleModules(): array
    {
        $values = $this->modules ?? [];

        if ($values === []) {
            return [$this->role];
        }

        return collect($values)
            ->map(fn (string $value) => UserRole::tryFrom($value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function hasModule(UserRole|string $module): bool
    {
        $value = $module instanceof UserRole ? $module->value : $module;

        return in_array($value, $this->moduleValues(), true);
    }

    /** @return list<string> */
    public function moduleValues(): array
    {
        if (is_array($this->modules) && $this->modules !== []) {
            return array_values($this->modules);
        }

        return [$this->role->value];
    }

    public function defaultModule(): UserRole
    {
        return $this->accessibleModules()[0] ?? $this->role;
    }

    public function syncModules(array $modules, ?UserRole $primary = null): void
    {
        $values = collect($modules)
            ->map(fn ($module) => $module instanceof UserRole ? $module->value : (string) $module)
            ->filter(fn (string $value) => UserRole::tryFrom($value) !== null)
            ->unique()
            ->values()
            ->all();

        if ($values === []) {
            $values = [UserRole::Cogs->value];
        }

        $this->modules = $values;
        $this->role = $primary ?? UserRole::from($values[0]);
    }

    public function isCogs(): bool
    {
        return $this->hasModule(UserRole::Cogs);
    }

    public function isKasir(): bool
    {
        return $this->hasModule(UserRole::Kasir);
    }

    public function isAdmin(): bool
    {
        return $this->hasModule(UserRole::Admin);
    }
}
