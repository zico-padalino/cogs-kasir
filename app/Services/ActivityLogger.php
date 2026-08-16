<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\PosOrder;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ActivityLogger
{
    /** @var list<string> */
    private array $redactedKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'pin',
        'token',
        'photo',
        'selfie',
        'remember_token',
    ];

    public function record(
        string $category,
        string $action,
        string $description,
        ?User $actor = null,
        ?Model $subject = null,
        array $properties = [],
        ?string $actorName = null,
        ?string $actorEmail = null,
        ?string $channel = null,
    ): void {
        try {
            if (! Schema::hasTable('activity_logs')) {
                return;
            }

            $request = $this->request();
            $user = $actor ?? $request?->user();

            ActivityLog::query()->create([
                'category' => $category,
                'action' => $action,
                'description' => mb_substr($description, 0, 255),
                'user_id' => $user?->id,
                'actor_name' => $actorName ?? $user?->name,
                'actor_email' => $actorEmail ?? $user?->email,
                'ip_address' => $request?->ip(),
                'user_agent' => mb_substr((string) $request?->userAgent(), 0, 1000) ?: null,
                'method' => $request?->method(),
                'url' => $request ? mb_substr($request->fullUrl(), 0, 2048) : null,
                'route_name' => $request?->route()?->getName(),
                'channel' => $channel ?? $this->detectChannel($request),
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'session_id' => $this->sessionId($request),
                'properties' => $this->mergeRequestMeta($request, $this->redact($properties)),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function fromLogin(Login $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        $name = $user?->name ?? 'Pengguna';

        $this->record(
            'auth',
            'login',
            $name.' masuk ke sistem.',
            $user,
            $user,
            [
                'guard' => $event->guard,
                'remember' => $event->remember,
            ],
        );
    }

    public function fromLogout(Logout $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        $name = $user?->name ?? 'Pengguna';

        $this->record(
            'auth',
            'logout',
            $name.' keluar dari sistem.',
            $user,
            $user,
            ['guard' => $event->guard],
        );
    }

    public function fromFailed(Failed $event): void
    {
        $email = (string) ($event->credentials['email'] ?? '');
        $user = $event->user instanceof User ? $event->user : null;

        $this->record(
            'auth',
            'login_failed',
            'Percobaan login gagal'.($email !== '' ? ' untuk '.$email : '').'.',
            $user,
            $user,
            [
                'guard' => $event->guard,
                'email' => $email,
            ],
            actorName: $user?->name,
            actorEmail: $user?->email ?: ($email !== '' ? $email : null),
        );
    }

    public function orderEvent(string $action, string $description, PosOrder $order, array $extra = []): void
    {
        $category = $order->source?->value === 'online' && in_array($action, [
            'order_submitted',
            'order_cash_kasir',
        ], true) ? 'pesan' : 'transaksi';

        if ($action === 'order_paid' && $order->source?->value === 'online' && ! auth()->check()) {
            $category = 'pesan';
        }

        $this->record(
            $category,
            $action,
            $description,
            subject: $order,
            properties: array_merge($this->orderProperties($order), $extra),
            actorName: $extra['actor_name'] ?? $order->cashier_name,
        );
    }

    /** @return array<string, mixed> */
    public function orderProperties(PosOrder $order): array
    {
        $order->loadMissing('table', 'items.product');

        return [
            'order_number' => $order->order_number,
            'status' => $order->status?->value,
            'source' => $order->source?->value,
            'order_type' => $order->order_type?->value,
            'customer' => $order->customer_note,
            'table' => $order->table?->label,
            'total' => (float) $order->total,
            'payment_method' => $order->payment_method?->value,
            'cashier_name' => $order->cashier_name,
            'item_count' => $order->items->count(),
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product?->name,
                'qty' => (float) $item->quantity,
                'total' => (float) $item->line_total,
            ])->values()->all(),
        ];
    }

    private function request(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        return $request instanceof Request ? $request : null;
    }

    private function detectChannel(?Request $request): string
    {
        if (! $request) {
            return 'system';
        }

        if ($request->is('api/*')) {
            return 'api';
        }

        if ($request->is('pesan*') || $request->routeIs('order.*')) {
            return 'pesan';
        }

        if ($request->is('kasir*') || $request->routeIs('kasir.*')) {
            return 'kasir';
        }

        if ($request->is('admin*') || $request->routeIs('admin.*')) {
            return 'admin';
        }

        if ($request->is('absensi*') || $request->routeIs('attendance.*')) {
            return 'absensi';
        }

        return 'web';
    }

    private function sessionId(?Request $request): ?string
    {
        try {
            if ($request?->hasSession()) {
                return substr((string) $request->session()->getId(), 0, 64) ?: null;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function mergeRequestMeta(?Request $request, array $properties): array
    {
        if (! $request) {
            return $properties;
        }

        return array_filter([
            ...$properties,
            'ip_forwarded_for' => $request->header('X-Forwarded-For'),
            'ip_real' => $request->header('X-Real-IP'),
            'referer' => $request->headers->get('referer'),
            'device' => $this->deviceLabel((string) $request->userAgent()),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function deviceLabel(string $ua): string
    {
        $ua = strtolower($ua);

        return match (true) {
            str_contains($ua, 'iphone') || str_contains($ua, 'ipad') => 'iOS',
            str_contains($ua, 'android') => 'Android',
            str_contains($ua, 'windows') => 'Windows',
            str_contains($ua, 'macintosh') || str_contains($ua, 'mac os') => 'Mac',
            str_contains($ua, 'linux') => 'Linux',
            $ua === '' => 'Tidak diketahui',
            default => 'Lainnya',
        };
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function redact(array $properties): array
    {
        $clean = [];

        foreach ($properties as $key => $value) {
            $name = strtolower((string) $key);
            if (in_array($name, $this->redactedKeys, true) || str_contains($name, 'password') || str_contains($name, 'token')) {
                $clean[$key] = '[disembunyikan]';
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->redact($value);
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }
}
