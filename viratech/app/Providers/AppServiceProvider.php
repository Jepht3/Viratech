<?php

namespace App\Providers;

use App\Services\LocalPaypalGateway;
use App\Services\PaypalGateway;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Hors ligne : facture PayPal simulée. La passerelle réelle (API PayPal Business) la remplacera en phase 2.
        $this->app->bind(PaypalGateway::class, LocalPaypalGateway::class);
    }

    public function boot(): void
    {
        //
    }
}
