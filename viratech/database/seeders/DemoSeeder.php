<?php

namespace Database\Seeders;

use App\Models\Corridor;
use App\Models\User;
use App\Services\OrderWorkflow;
use Illuminate\Database\Seeder;

/** Données de démonstration locales : php artisan db:seed --class=DemoSeeder (après DatabaseSeeder). */
class DemoSeeder extends Seeder
{
    public function run(OrderWorkflow $wf): void
    {
        $c = User::where('email', 'client@viratech.test')->firstOrFail();
        $op = User::where('email', 'operateur@viratech.test')->firstOrFail();
        $m = fn (string $k) => $c->payoutMethods()->where('kind', $k)->firstOrFail();
        $cor = fn (string $code) => Corridor::where('code', $code)->firstOrFail();

        // Terminée
        $o = $wf->create($c, $cor('paypal_equity'), 200, $m('equity'));
        $wf->complete($o, 'payment_received', 'system');
        $wf->complete($o, 'security_check', 'operator', $op);
        $wf->complete($o, 'payout_in_progress', 'operator', $op);
        $wf->complete($o, 'payout_done', 'operator', $op, 'EQ-90311');

        // En contrôle de sécurité
        $o2 = $wf->create($c, $cor('paypal_mobile'), 220, $m('mpesa'));
        $wf->complete($o2, 'payment_received', 'system');

        // En attente du dépôt du client
        $wf->create($c, $cor('mobile_paypal'), 100, $m('paypal'), 'airtel');
    }
}
