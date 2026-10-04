<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Comptes de réception de l'entreprise, un par réseau : PayPal, Equity et chaque mobile money (M-Pesa, Airtel Money, Orange Money,
 * Afrimoney) ont chacun leur propre numéro. L'administrateur peut ajouter, modifier ou désactiver ces numéros.
 * Ils ne sont montrés au client que lorsque FlexPay est désactivé (avec FlexPay, le paiement passe par l'écran automatique FlexPay).
 */
class CompanyAccount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public const KINDS = [
        'paypal' => 'PayPal',
        'equity' => 'Equity',
        'mpesa' => 'M-Pesa',
        'airtel' => 'Airtel Money',
        'orange' => 'Orange Money',
        'afrimoney' => 'Afrimoney',
    ];

    /** Premier compte actif d'un réseau. */
    public static function forKind(string $kind): ?self
    {
        return static::where('kind', $kind)->where('is_active', true)->orderBy('id')->first();
    }

    /** Compte vers lequel le client doit payer pour cette commande : le numéro du réseau qu'il a choisi (M-Pesa, Airtel, etc.). */
    public static function forOrder(Order $order): ?self
    {
        $order->loadMissing('corridor');
        $kind = match ($order->corridor->source_kind) {
            'mobile_money' => (string) $order->source_kind,
            'equity' => 'equity',
            default => 'paypal',
        };

        return $kind !== '' ? static::forKind($kind) : null;
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}