<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const CATEGORIES = [
        'auth' => 'Login / akun',
        'transaksi' => 'Transaksi',
        'pesan' => 'Pesan meja',
        'kasir' => 'PIN kasir',
        'absensi' => 'Absensi',
        'akun' => 'Akses akun',
    ];

    public const ACTIONS = [
        'login' => 'Login berhasil',
        'login_failed' => 'Login gagal',
        'login_rejected' => 'Login ditolak',
        'logout' => 'Logout',
        'module_switch' => 'Ganti modul',
        'pin_unlock' => 'Buka PIN kasir',
        'pin_unlock_failed' => 'PIN kasir salah',
        'pin_lock' => 'Kunci PIN kasir',
        'order_submitted' => 'Pesanan dikirim',
        'order_cash_kasir' => 'Pesan tunai ke kasir',
        'order_confirmed' => 'Pesanan dikonfirmasi',
        'order_paid' => 'Transaksi lunas',
        'order_cancelled' => 'Pesanan dibatalkan',
        'order_reopened' => 'Transaksi dibuka ulang',
        'attendance_in' => 'Absen masuk',
        'attendance_out' => 'Absen pulang',
        'user_created' => 'Akun dibuat',
        'user_updated' => 'Akun diubah',
        'user_deleted' => 'Akun dihapus',
        'password_reset' => 'Reset password',
    ];

    protected $fillable = [
        'category',
        'action',
        'description',
        'user_id',
        'actor_name',
        'actor_email',
        'ip_address',
        'user_agent',
        'method',
        'url',
        'route_name',
        'channel',
        'subject_type',
        'subject_id',
        'session_id',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? str_replace('_', ' ', $this->action);
    }

    public function subjectLabel(): ?string
    {
        if (! $this->subject_type || ! $this->subject_id) {
            return null;
        }

        $short = class_basename($this->subject_type);

        return $short.' #'.$this->subject_id;
    }
}
