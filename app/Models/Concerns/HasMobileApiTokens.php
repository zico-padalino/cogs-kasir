<?php

namespace App\Models\Concerns;

use App\Models\PersonalAccessToken;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

trait HasMobileApiTokens
{
    public ?PersonalAccessToken $accessToken = null;

    public function tokens(): MorphMany
    {
        return $this->morphMany(PersonalAccessToken::class, 'tokenable');
    }

    /**
     * @param  list<string>  $abilities
     * @return object{accessToken: PersonalAccessToken, plainTextToken: string}
     */
    public function createToken(string $name, array $abilities = ['*'], $expiresAt = null): object
    {
        $plainTextToken = Str::random(40);

        $accessToken = $this->tokens()->create([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        return (object) [
            'accessToken' => $accessToken,
            'plainTextToken' => $accessToken->getKey().'|'.$plainTextToken,
        ];
    }

    public function currentAccessToken(): ?PersonalAccessToken
    {
        return $this->accessToken ?? null;
    }

    public function withAccessToken(?PersonalAccessToken $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function tokenCan(string $ability): bool
    {
        return $this->currentAccessToken()?->can($ability) ?? false;
    }
}
