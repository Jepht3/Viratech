<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStep extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'done_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by_id');
    }

    public function actorLabel(): string
    {
        return ['system' => 'Système', 'client' => 'Vous', 'operator' => 'Opérateur'][$this->done_by_type ?? $this->actor] ?? $this->actor;
    }
}
