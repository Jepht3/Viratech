<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'percent_applied' => 'decimal:2', 'percent_fee' => 'decimal:2',
            'fixed_fee' => 'decimal:2', 'total_fee' => 'decimal:2', 'net_amount' => 'decimal:2',
            'fees_locked_until' => 'datetime', 'expires_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public const STATUSES = [
        'active' => 'En cours', 'completed' => 'Terminé', 'rejected' => 'Refusé', 'expired' => 'Expiré', 'cancelled' => 'Annulé',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function corridor(): BelongsTo
    {
        return $this->belongsTo(Corridor::class);
    }

    public function payoutMethod(): BelongsTo
    {
        return $this->belongsTo(PayoutMethod::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(OrderStep::class)->orderBy('position');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(OrderProof::class)->latest();
    }

    /** Première étape non terminée (null si tout est fait). */
    public function currentStep(): ?OrderStep
    {
        return $this->steps->firstWhere(fn (OrderStep $s) => $s->status !== 'done');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
