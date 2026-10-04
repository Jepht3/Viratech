<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Passerelle FlexPay réelle.
 *
 * ATTENTION : les adresses et les champs ci-dessous viennent de la bibliothèque PHP tierce « devscast/flexpay-php » (la
 * documentation officielle de FlexPay n'est pas publique). Ils doivent être validés avec FlexPay et testés d'abord en
 * environnement « sandbox » avec un petit montant. Le versement (payout) doit en plus être activé sur votre compte marchand.
 *
 * Réglages (administrateur) : flexpay.environment (live|sandbox), flexpay.merchant, flexpay.token, flexpay.callback_secret.
 */
class HttpFlexpayGateway implements FlexpayGateway
{
    private const HOSTS = [
        'live' => ['api' => 'https://backend.flexpay.cd/api/rest/v1', 'card' => 'https://cardpayment.flexpay.cd/v1.1/pay'],
        'sandbox' => ['api' => 'https://beta-backend.flexpay.cd/api/rest/v1', 'card' => 'https://beta-cardpayment.flexpay.cd/v1.1/pay'],
    ];

    public function charge(Order $order, string $method, ?string $phone = null): array
    {
        $c = $this->config();
        $callback = $this->callbackUrl($c);

        if ($method === 'flexpay_mobile') {
            $r = $this->http($c)->post($c['api'].'/paymentService', [
                'merchant' => $c['merchant'], 'type' => 1, 'phone' => $this->phone($phone), 'reference' => $order->reference,
                'amount' => (string) $order->amount, 'currency' => 'USD', 'callbackUrl' => $callback,
                'description' => 'Commande '.$order->reference,
            ]);
            $this->assertOk($r, 'FlexPay a refusé la demande de paiement');

            return ['reference' => (string) ($r->json('orderNumber') ?: $order->reference), 'url' => null];
        }

        $home = url('/commandes/'.$order->reference);
        $r = $this->http($c)->post($c['card'], [
            'authorization' => 'Bearer '.$c['token'], 'merchant' => $c['merchant'], 'reference' => $order->reference,
            'amount' => (string) $order->amount, 'currency' => 'USD', 'description' => 'Commande '.$order->reference,
            'callback_url' => $callback, 'approve_url' => $home, 'cancel_url' => $home, 'decline_url' => $home, 'home_url' => $home,
        ]);
        $this->assertOk($r, 'FlexPay a refusé le paiement par carte');
        if (! $r->json('url')) {
            throw new RuntimeException('FlexPay n\'a pas renvoyé la page de paiement par carte.');
        }

        return ['reference' => (string) ($r->json('orderNumber') ?: $order->reference), 'url' => (string) $r->json('url')];
    }

    public function payout(Order $order, string $phone): array
    {
        $c = $this->config();
        if (! Setting::bool('flexpay.payout_enabled')) {
            throw new RuntimeException('Le versement FlexPay n\'est pas activé dans les paramètres.');
        }
        $r = $this->http($c)->post($c['api'].'/merchantPayOutService', [
            'merchant' => $c['merchant'], 'type' => 1, 'reference' => $order->reference.'-OUT', 'phone' => $this->phone($phone),
            'amount' => (string) $order->net_amount, 'currency' => 'USD', 'callbackUrl' => $this->callbackUrl($c),
        ]);
        $this->assertOk($r, 'FlexPay a refusé le versement');

        return ['reference' => (string) ($r->json('orderNumber') ?: $order->reference.'-OUT')];
    }

    public function status(string $reference): string
    {
        $c = $this->config();
        $r = $this->http($c)->get($c['api'].'/check/'.$reference);
        if (! $r->successful()) {
            return 'pending';
        }

        return match ((string) $r->json('transaction.status')) {
            '0' => 'paid',
            '1' => 'failed',
            default => 'pending',
        };
    }

    private function http(array $c)
    {
        return Http::withToken($c['token'])->acceptJson()->asJson()->timeout(30);
    }

    private function assertOk($r, string $msg): void
    {
        if (! $r->successful() || (string) $r->json('code') !== '0') {
            throw new RuntimeException($msg.' : '.($r->json('message') ?: 'erreur '.$r->status()));
        }
    }

    /** Numéro au format 243XXXXXXXXX (12 chiffres) attendu par FlexPay. */
    private function phone(?string $phone): string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        }
        if (str_starts_with($d, '0') && strlen($d) === 10) {
            $d = '243'.substr($d, 1);
        }
        if (strlen($d) === 9) {
            $d = '243'.$d;
        }
        if (strlen($d) !== 12) {
            throw new RuntimeException('Numéro de téléphone invalide (format attendu : 243XXXXXXXXX).');
        }

        return $d;
    }

    private function callbackUrl(array $c): string
    {
        return url('/api/webhooks/flexpay?secret='.urlencode((string) $c['callback_secret']));
    }

    private function config(): array
    {
        $env = Setting::get('flexpay.environment', 'sandbox') === 'live' ? 'live' : 'sandbox';
        $c = [
            'merchant' => Setting::get('flexpay.merchant'), 'token' => Setting::get('flexpay.token'),
            'callback_secret' => Setting::get('flexpay.callback_secret'), ...self::HOSTS[$env],
        ];
        if (! Setting::bool('flexpay.enabled') || blank($c['merchant']) || blank($c['token'])) {
            throw new RuntimeException('FlexPay n\'est pas configuré : renseignez-le dans les paramètres de l\'administrateur.');
        }

        return $c;
    }
}