<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyAccount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Compte actif pour un type de canal (paypal, equity, mobile_money). */
    public static function forKind(string $kind): ?self
    {
        return static::where('kind', $kind)->where('is_active', true)->orderBy('id')->first();
    }
}
