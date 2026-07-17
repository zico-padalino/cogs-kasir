<?php

/**
 * Stub minimal agar boot Laravel tidak fatal jika cache discovery
 * masih mereferensikan laravel/sanctum padahal paket belum ada di vendor
 * (kasus umum deploy DomaiNesia tanpa composer install).
 */
namespace Laravel\Sanctum;

if (! class_exists(SanctumServiceProvider::class, false)) {
    class SanctumServiceProvider extends \Illuminate\Support\ServiceProvider
    {
        public function register(): void
        {
            // no-op
        }

        public function boot(): void
        {
            // no-op
        }
    }
}

if (! class_exists(Sanctum::class, false)) {
    class Sanctum
    {
        public static function currentApplicationUrlWithPort(): string
        {
            $appUrl = rtrim((string) config('app.url', 'http://localhost'), '/');

            return ','.str_replace(['http://', 'https://'], '', $appUrl);
        }

        public static function actingAs($user, $abilities = [], $guard = 'sanctum')
        {
            return $user;
        }
    }
}

if (! trait_exists(HasApiTokens::class, false)) {
    trait HasApiTokens
    {
        public function tokens()
        {
            return $this->morphMany(\App\Models\PersonalAccessToken::class, 'tokenable');
        }

        public function tokenCan(string $ability)
        {
            return true;
        }

        public function createToken(string $name, array $abilities = ['*'], $expiresAt = null)
        {
            throw new \RuntimeException('Gunakan App\\Models\\Concerns\\HasMobileApiTokens.');
        }

        public function currentAccessToken()
        {
            return null;
        }

        public function withAccessToken($accessToken)
        {
            return $this;
        }
    }
}
