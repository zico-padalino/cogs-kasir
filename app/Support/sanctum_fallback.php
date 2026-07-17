<?php

/**
 * Fallback jika laravel/sanctum belum ada di vendor (sering terjadi setelah deploy tanpa composer install).
 * Mencegah fatal error yang membuat seluruh situs HTTP 500.
 */
namespace Laravel\Sanctum;

$sanctumTraitFile = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'laravel'
    .DIRECTORY_SEPARATOR.'sanctum'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'HasApiTokens.php';

if (is_file($sanctumTraitFile)) {
    return;
}

if (! trait_exists(HasApiTokens::class, false)) {
    trait HasApiTokens
    {
        public function tokens()
        {
            return $this->morphMany(PersonalAccessToken::class, 'tokenable');
        }

        public function tokenCan(string $ability)
        {
            return true;
        }

        public function createToken(string $name, array $abilities = ['*'], $expiresAt = null)
        {
            throw new \RuntimeException(
                'Paket laravel/sanctum belum terpasang di server. Jalankan: composer require laravel/sanctum && php artisan migrate'
            );
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

if (! class_exists(PersonalAccessToken::class, false)) {
    class PersonalAccessToken extends \Illuminate\Database\Eloquent\Model
    {
        protected $table = 'personal_access_tokens';
    }
}
