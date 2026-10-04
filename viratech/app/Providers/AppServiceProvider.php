<?php

namespace App\Providers;

use App\Models\Setting;
use App\Services\FlexpayGateway;
use App\Services\HttpFlexpayGateway;
use App\Services\LocalFlexpayGateway;
use App\Services\LocalPaypalGateway;
use App\Services\PaypalGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Hors ligne : factures PayPal et paiements FlexPay simulés. Les passerelles réelles les remplacent quand elles sont activées.
        $this->app->bind(PaypalGateway::class, LocalPaypalGateway::class);
        $this->app->bind(FlexpayGateway::class, fn () => Setting::bool('flexpay.enabled') ? new HttpFlexpayGateway : new LocalFlexpayGateway);
    }

    public function boot(): void
    {
        // Emails : si l'administrateur a saisi la clé Resend dans les paramètres, elle remplace le réglage du fichier .env.
        {
            $key = Setting::get('mail.resend_key');
            if ($key) {
                config([
                    'mail.default' => 'resend',
                    'mail.mailers.resend' => ['transport' => 'resend'],
                    'services.resend.key' => $key,
                ]);
            }
            if ($from = Setting::get('mail.from_address')) {
                config(['mail.from.address' => $from, 'mail.from.name' => Setting::get('mail.from_name', 'Viratech')]);
            }
        }
    }
}