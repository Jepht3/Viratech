<?php

use App\Services\OrderWorkflow;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('orders:expire', function (OrderWorkflow $workflow) {
    $this->info($workflow->expireStale().' commande(s) expirée(s).');
})->purpose('Expire les commandes sans action du client dans le délai imparti');

Schedule::command('orders:expire')->everyMinute();
