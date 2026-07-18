<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevicePushToken extends Model
{
    public const PLATFORM_EXPO = 'expo';

    public const PLATFORM_FCM = 'fcm';

    public const PLATFORM_WEB = 'web';

    protected $fillable = [
        'user_id',
        'platform',
        'token_hash',
        'token',
        'web_p256dh',
        'web_auth',
        'device_name',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            if (filled($model->token)) {
                $model->token_hash = self::hashToken((string) $model->token);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
