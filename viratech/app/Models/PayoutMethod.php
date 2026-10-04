<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutMethod extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_verified' => 'boolean'];
    }

    public const KINDS = [
        'equity' => 'Equity',
        'mpesa' => 'M-Pesa',
        'airtel' => 'Airtel Money',
        'orange' => 'Orange Money',
        'afrimoney' => 'Afrimoney',
        'paypal' => 'PayPal',
    ];

    public const MOBILE = ['mpesa', 'airtel', 'orange', 'afrimoney'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    /** Types de moyens acceptés comme destination d'un couloir. */
    public static function kindsForTarget(string $target): array
    {
        return match ($target) {
            'equity' => ['equity'],
            'mobile_money' => self::MOBILE,
            'paypal' => ['paypal'],
            default => [],
        };
    }

    /** Numéro masqué pour l'affichage (••• 4521). */
    public function masked(): string
    {
        $v = $this->account_value;
        if ($this->kind === 'paypal') {
            return preg_replace('/(?<=.{2}).(?=[^@]*@)/', '•', $v);
        }

        return strlen($v) > 4 ? '••• '.substr($v, -4) : $v;
    }
}
