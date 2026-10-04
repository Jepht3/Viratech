<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeTier extends Model
{
    protected $guarded = [];

    public function corridor(): BelongsTo
    {
        return $this->belongsTo(Corridor::class);
    }
}
