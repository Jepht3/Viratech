<?php

namespace Database\Seeders;

use App\Models\Corridor;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/** Données de démonstration locales : php artisan db:seed --class=DemoSeeder (après DatabaseSeeder). */
class DemoSeeder extends Seeder
{
    public function run(OrderWorkflow $wf): void
    {
        $c = User::where('email', 'client@viratech.test')->firstOrFail();
        $op = User::where('email', 'operateur@viratech.test')->firstOrFail();
        $m = fn (string $k) => $c->payoutMethods()->where('kind', $k)->firstOrFail();
        $cor = fn (string $code) => Corridor::where('code', $code)->firstOrFail();

        // 1. Terminée : PayPal vers Equity (facture payée, vérifiée, versée avec capture). Le délai de sécurité est levé pour la démo.
        $o = $wf->create($c, $cor('paypal_equity'), 200, $m('equity'), null, 'paypal_invoice');
        $wf->complete($o, 'client_payment', 'system', null, 'FACTURE-PAYEE');
        $wf->complete($o, 'payment_verified', 'operator', $op);
        Order::whereKey($o->id)->update(['payout_not_before' => null]);
        $o = $o->fresh(['steps', 'corridor', 'user']);
        $wf->complete($o, 'security_check', 'operator', $op);
        $wf->complete($o, 'payout_in_progress', 'operator', $op);
        $wf->complete($o, 'payout_done', 'operator', $op, 'EQ-90311', $this->capture('Versement EQ-90311'));

        // 2. En délai de sécurité : PayPal vers M-Pesa, paiement déjà vérifié (les fonds sont gardés avant versement).
        $o2 = $wf->create($c, $cor('paypal_mobile'), 220, $m('mpesa'), null, 'paypal_account');
        $wf->complete($o2, 'client_payment', 'client', $c, 'PAYPAL-7TX48920', $this->capture('Capture PayPal 220 USD'));
        $wf->complete($o2, 'payment_verified', 'operator', $op);

        // 3. En attente du paiement du client : mobile money vers PayPal.
        $wf->create($c, $cor('mobile_paypal'), 100, $m('paypal'), 'airtel', 'transfer');
    }

    /** Petite image de démonstration enregistrée comme « capture ». */
    private function capture(string $text): string
    {
        $im = imagecreatetruecolor(480, 260);
        imagefill($im, 0, 0, imagecolorallocate($im, 245, 247, 250));
        imagefilledrectangle($im, 0, 0, 480, 44, imagecolorallocate($im, 28, 36, 41));
        imagestring($im, 4, 14, 14, 'Viratech - capture de demonstration', imagecolorallocate($im, 255, 255, 255));
        imagestring($im, 5, 14, 110, $text, imagecolorallocate($im, 8, 146, 75));
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        $path = 'proofs/demo-'.md5($text).'.png';
        Storage::put($path, $png);

        return $path;
    }
}