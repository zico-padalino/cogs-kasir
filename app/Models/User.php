<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Support\CogsNavigation;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'role', 'modules', 'is_root', 'password', 'must_change_password'])]
#[Hidden(['password', 'remember_token', 'pin_hash'])]
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
            'is_root' => 'boolean',
            'pin_set_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Akun root punya akses ke seluruh modul dan wajib memilih modul saat login.
     */
    public function isRoot(): bool
    {
        return (bool) $this->is_root;
    }

    /** @return list<UserRole> */
    public function accessibleModules(): array
    {
        $values = $this->moduleValues();

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
        if ($this->isRoot()) {
            return array_map(fn (UserRole $role) => $role->value, UserRole::cases());
        }

        if (is_array($this->modules) && $this->modules !== []) {
            return array_values($this->modules);
        }

        if ($this->role instanceof UserRole) {
            return [$this->role->value];
        }

        return [];
    }

    public function defaultModule(): UserRole
    {
        if ($this->role instanceof UserRole && $this->hasModule($this->role)) {
            return $this->role;
        }

        return $this->accessibleModules()[0] ?? UserRole::Cogs;
    }

    public function homeUrl(): string
    {
        if ($this->hasModule(UserRole::Admin)) {
            return route('admin.dashboard');
        }

        $module = $this->defaultModule();

        if ($module === UserRole::Cogs) {
            return CogsNavigation::preferredUrl();
        }

        return route($module->homeRoute());
    }

    /**
     * Setelah login: prioritaskan kasir (PIN) jika punya akses.
     */
    public function preferredLoginUrl(): string
    {
        if ($this->hasModule(UserRole::Kasir)) {
            return route('kasir.index');
        }

        return $this->homeUrl();
    }

    /**
     * Tujuan setelah autentikasi berhasil. Akun root selalu diarahkan ke
     * pemilih modul (hub) supaya bisa memilih modul yang ingin dibuka.
     */
    public function postAuthUrl(): string
    {
        if ($this->isRoot()) {
            return route('hub');
        }

        return $this->preferredLoginUrl();
    }

    public function preferredLoginModule(): UserRole
    {
        if ($this->hasModule(UserRole::Kasir)) {
            return UserRole::Kasir;
        }

        if ($this->hasModule(UserRole::Admin)) {
            return UserRole::Admin;
        }

        return $this->defaultModule();
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

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
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
