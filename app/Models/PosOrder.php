<?php

namespace App\Models;

use App\Enums\PosOrderSource;
use App\Enums\PosOrderStatus;
use App\Enums\PosOrderType;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosOrder extends Model
{
    protected $fillable = [
        'order_number',
        'order_day',
        'pos_table_id',
        'source',
        'order_type',
        'status',
        'customer_note',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'total',
        'amount_received',
        'change_amount',
        'payment_method',
        'payment_proof_path',
        'paid_at',
        'confirmed_at',
        'confirmed_by',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'source' => PosOrderSource::class,
            'order_type' => PosOrderType::class,
            'status' => PosOrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'order_day' => 'date',
            'subtotal' => 'decimal:4',
            'discount_value' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'total' => 'decimal:4',
            'amount_received' => 'decimal:4',
            'change_amount' => 'decimal:4',
            'paid_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(PosTable::class, 'pos_table_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosOrderItem::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function salesTransactions(): HasMany
    {
        return $this->hasMany(SalesTransaction::class);
    }

    public function isEditable(): bool
    {
        return $this->status === PosOrderStatus::Open;
    }

    public function isKasirEditable(): bool
    {
        if ($this->source === PosOrderSource::Online) {
            return false;
        }

        return $this->status === PosOrderStatus::Open;
    }

    public function needsKasirConfirmation(): bool
    {
        return $this->source === PosOrderSource::Online
            && $this->status === PosOrderStatus::Submitted;
    }

    /** Pesanan online yang sudah masuk antrean kasir dan siap dilayani/bayar. */
    public function isAwaitingKasirService(): bool
    {
        return $this->source === PosOrderSource::Online
            && in_array($this->status, [PosOrderStatus::Submitted, PosOrderStatus::Confirmed], true);
    }

    public function canCheckoutAtKasir(): bool
    {
        return match ($this->source) {
            PosOrderSource::Kasir => $this->status === PosOrderStatus::Open,
            // Online: bayar setelah masuk kasir (submitted/confirmed), bukan konfirmasi dulu.
            PosOrderSource::Online => in_array($this->status, [
                PosOrderStatus::Submitted,
                PosOrderStatus::Confirmed,
            ], true),
            default => false,
        };
    }

    public function paymentProofUrl(): ?string
    {
        if (! filled($this->payment_proof_path)) {
            return null;
        }

        return asset('storage/'.$this->payment_proof_path);
    }

    public function requiresPaymentProof(): bool
    {
        return in_array($this->payment_method, [PaymentMethod::Qris, PaymentMethod::Transfer], true);
    }

    public function hasDiscount(): bool
    {
        return (float) $this->discount_amount > 0;
    }
}
