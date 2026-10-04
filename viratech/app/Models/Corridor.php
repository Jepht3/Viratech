<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Corridor extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'coming_soon' => 'boolean'];
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(FeeTier::class)->orderBy('min_amount');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Un retrait (PayPal vers un compte local) ou un dépôt vers PayPal. */
    public function isWithdrawal(): bool
    {
        return $this->source_kind === 'paypal';
    }

    public function etaLabel(): string
    {
        $fmt = fn (int $m) => $m >= 1440 ? intdiv($m, 1440).' jour'.($m >= 2880 ? 's' : '') : ($m >= 60 && $m % 60 === 0 ? intdiv($m, 60).' h' : $m.' min');

        return $fmt($this->eta_min_minutes).' à '.$fmt($this->eta_max_minutes);
    }
}
