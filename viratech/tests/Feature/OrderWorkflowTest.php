<?php

namespace Tests\Feature;

use App\Models\Corridor;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\User;
use App\Services\FlexpayGateway;
use App\Services\HoldPolicy;
use App\Services\OrderWorkflow;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private OrderWorkflow $wf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        $this->wf = app(OrderWorkflow::class);
    }

    private function client(): User
    {
        return User::where('email', 'client@viratech.test')->first();
    }

    private function operator(): User
    {
        return User::where('email', 'operateur@viratech.test')->first();
    }

    private function fails(callable $fn): void
    {
        try {
            $fn();
            $this->fail('Une exception était attendue.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }
    }

    public function test_paypal_vers_mobile_money_exige_les_preuves_des_deux_cotes_et_le_delai_de_securite(): void
    {
        $client = $this->client();
        $order = $this->wf->create($client, Corridor::where('code', 'paypal_mobile')->first(), 200, $client->payoutMethods()->where('kind', 'mpesa')->first(), null, 'paypal_account');

        $this->assertSame('178.00', $order->net_amount);
        $this->assertSame('client_payment', $order->currentStep()->key);

        // Le client doit joindre la capture de son paiement ; l'opérateur ne peut pas valider à sa place.
        $this->fails(fn () => $this->wf->complete($order, 'client_payment', 'client', $client, 'REF'));
        $this->fails(fn () => $this->wf->complete($order, 'client_payment', 'operator', $this->operator(), 'REF', 'proofs/x.png'));
        $order = $this->wf->complete($order, 'client_payment', 'client', $client, 'PP-1', 'proofs/client.png');

        $order = $this->wf->complete($order, 'payment_verified', 'operator', $this->operator());
        // Client peu fiable (moins de 3 échanges, identité non vérifiée) : délai de sécurité doublé = 14 jours.
        $this->assertTrue($order->payout_not_before->between(now()->addDays(13), now()->addDays(15)));
        $this->fails(fn () => $this->wf->complete($order, 'security_check', 'operator', $this->operator()));

        // Levée du délai par un administrateur uniquement.
        $this->fails(fn () => $this->wf->releaseHold($order, $this->operator(), 'test'));
        $this->wf->releaseHold($order, User::where('email', 'admin@viratech.test')->first(), 'Client de confiance');
        $order = $order->fresh(['steps', 'corridor', 'user']);

        $order = $this->wf->complete($order, 'security_check', 'operator', $this->operator());
        $order = $this->wf->complete($order, 'payout_in_progress', 'operator', $this->operator());
        $this->fails(fn () => $this->wf->complete($order, 'payout_done', 'operator', $this->operator(), 'MP1'));            // sans capture
        $order = $this->wf->complete($order, 'payout_done', 'operator', $this->operator(), 'MP1', 'proofs/payout.png');

        $this->assertSame('completed', $order->status);
        $this->assertSame(['client_payment', 'operator_payout'], $order->proofs->pluck('kind')->sort()->values()->all());
        $this->assertEquals(LedgerEntry::sum('debit'), LedgerEntry::sum('credit'));
        $this->assertSame('22.00', number_format((float) LedgerEntry::where('account', 'company:fees')->sum('credit'), 2, '.', ''));
    }

    public function test_les_paiements_hors_paypal_sont_traites_sans_delai(): void
    {
        $client = $this->client();
        $order = $this->wf->create($client, Corridor::where('code', 'mobile_paypal')->first(), 100, $client->payoutMethods()->where('kind', 'paypal')->first(), 'mpesa', 'transfer');
        $this->assertSame('88.00', $order->net_amount);
        $this->fails(fn () => $this->wf->complete($order, 'client_payment', 'client', $client));
        $order = $this->wf->complete($order, 'client_payment', 'client', $client, null, 'proofs/c.png');
        $order = $this->wf->complete($order, 'payment_verified', 'operator', $this->operator());
        $this->assertNull($order->payout_not_before);
        $this->wf->complete($order, 'security_check', 'operator', $this->operator());
    }

    public function test_le_delai_de_securite_depend_de_la_fiabilite_du_client(): void
    {
        $client = $this->client();
        $corridor = Corridor::where('code', 'paypal_equity')->first();
        $order = $this->wf->create($client, $corridor, 200, $client->payoutMethods()->where('kind', 'equity')->first(), null, 'paypal_invoice');
        $policy = app(HoldPolicy::class);

        $this->assertSame(14 * 1440, $policy->minutes($order));       // nouveau client : ×2

        $client->update(['kyc_level' => 2]);
        foreach (range(1, 3) as $i) {
            Order::create(['reference' => 'X'.$i, 'user_id' => $client->id, 'corridor_id' => $corridor->id, 'status' => 'completed', 'amount' => 150, 'percent_applied' => 10, 'percent_fee' => 15, 'fixed_fee' => 0, 'total_fee' => 15, 'net_amount' => 135, 'payout_kind' => 'equity', 'payout_account' => '1', 'payout_holder' => 'x']);
        }
        $this->assertSame(7 * 1440, $policy->minutes($order->fresh()));   // vérifié, 3 échanges : standard

        foreach (range(4, 10) as $i) {
            Order::create(['reference' => 'X'.$i, 'user_id' => $client->id, 'corridor_id' => $corridor->id, 'status' => 'completed', 'amount' => 150, 'percent_applied' => 10, 'percent_fee' => 15, 'fixed_fee' => 0, 'total_fee' => 15, 'net_amount' => 135, 'payout_kind' => 'equity', 'payout_account' => '1', 'payout_holder' => 'x']);
        }
        $this->assertSame(3 * 1440 + 720, $policy->minutes($order->fresh()));   // très fiable : moitié
    }

    public function test_paiement_flexpay_confirme_automatiquement_et_versement_flexpay(): void
    {
        $client = $this->client();
        $order = $this->wf->create($client, Corridor::where('code', 'mobile_paypal')->first(), 100, $client->payoutMethods()->where('kind', 'paypal')->first(), 'mpesa', 'flexpay_mobile');
        $this->assertStringContainsString('FlexPay', $order->currentStep()->pending_label);

        // Le client ne peut pas se déclarer payé lui-même.
        $this->fails(fn () => $this->wf->complete($order, 'client_payment', 'client', $client, null, 'proofs/c.png'));

        $order = $this->wf->startFlexpay($order, '+243810000000');
        $this->assertNotNull($order->flexpay_reference);
        $order = $this->wf->confirmFlexpay($order, trustSimulation: true);
        $this->assertSame('payment_verified', $order->currentStep()->key);
        $this->assertSame('system', $order->steps->firstWhere('key', 'client_payment')->done_by_type);
    }

    public function test_la_confirmation_flexpay_verifie_l_etat_reel_chez_flexpay(): void
    {
        $client = $this->client();
        $order = $this->wf->create($client, Corridor::where('code', 'mobile_paypal')->first(), 100, $client->payoutMethods()->where('kind', 'paypal')->first(), 'mpesa', 'flexpay_mobile');
        $order = $this->wf->startFlexpay($order, '+243810000000');

        // FlexPay répond « en attente » : un rappel ne suffit pas à valider.
        $this->assertSame('client_payment', $this->wf->confirmFlexpay($order)->currentStep()->key);

        // FlexPay répond « payé » : l'étape est validée.
        $this->app->bind(FlexpayGateway::class, fn () => new class implements FlexpayGateway
        {
            public function charge(Order $o, string $m, ?string $p = null): array { return ['reference' => 'X', 'url' => null]; }

            public function payout(Order $o, string $p): array { return ['reference' => 'Y']; }

            public function status(string $r): string { return 'paid'; }
        });
        $wf = app(OrderWorkflow::class);
        $this->assertSame('payment_verified', $wf->confirmFlexpay($order->fresh())->currentStep()->key);
    }

    public function test_versement_flexpay_vers_le_mobile_money(): void
    {
        $client = $this->client();
        $order = $this->wf->create($client, Corridor::where('code', 'mobile_paypal')->first(), 100, $client->payoutMethods()->where('kind', 'paypal')->first(), 'mpesa', 'transfer');
        // Cible PayPal : pas de versement FlexPay.
        foreach (['client_payment' => ['client', $client, null, 'p.png'], 'payment_verified' => ['operator', $this->operator(), null, null], 'security_check' => ['operator', $this->operator(), null, null], 'payout_in_progress' => ['operator', $this->operator(), null, null]] as $k => [$a, $by, $ref, $pf]) {
            $order = $this->wf->complete($order, $k, $a, $by, $ref, $pf);
        }
        $this->fails(fn () => $this->wf->payoutViaFlexpay($order, $this->operator()));

        // Cible mobile money : versement par FlexPay, confirmé sans capture manuelle.
        $o2 = $this->wf->create($client, Corridor::where('code', 'paypal_mobile')->first(), 200, $client->payoutMethods()->where('kind', 'mpesa')->first(), null, 'paypal_invoice');
        $o2 = $this->wf->complete($o2, 'client_payment', 'system');
        $o2 = $this->wf->complete($o2, 'payment_verified', 'operator', $this->operator());
        Order::whereKey($o2->id)->update(['payout_not_before' => null]);
        $o2 = $o2->fresh(['steps', 'corridor', 'user']);
        $o2 = $this->wf->complete($o2, 'security_check', 'operator', $this->operator());
        $o2 = $this->wf->complete($o2, 'payout_in_progress', 'operator', $this->operator());
        $o2 = $this->wf->payoutViaFlexpay($o2, $this->operator());
        $this->assertSame('flexpay', $o2->payout_via);
        $o2 = $this->wf->confirmFlexpayPayout($o2, trustSimulation: true);
        $this->assertSame('completed', $o2->status);
    }

    public function test_email_non_verifie_ne_peut_pas_echanger(): void
    {
        $client = $this->client();
        $client->update(['email_verified_at' => null]);
        $this->fails(fn () => $this->wf->create($client->fresh(), Corridor::where('code', 'paypal_equity')->first(), 200, $client->payoutMethods()->where('kind', 'equity')->first()));
    }

    public function test_facon_de_payer_non_autorisee_est_refusee(): void
    {
        $client = $this->client();
        $this->fails(fn () => $this->wf->create($client, Corridor::where('code', 'paypal_equity')->first(), 200, $client->payoutMethods()->where('kind', 'equity')->first(), null, 'flexpay_card'));
    }

    public function test_blocage_refus_et_expiration(): void
    {
        $client = $this->client();
        $order = $this->wf->create($client, Corridor::where('code', 'paypal_equity')->first(), 300, $client->payoutMethods()->where('kind', 'equity')->first(), null, 'paypal_account');

        $this->wf->block($order, $this->operator(), 'Capture illisible, merci de renvoyer.');
        $order->refresh()->load('steps');
        $this->assertSame('blocked', $order->currentStep()->status);
        $this->fails(fn () => $this->wf->complete($order, 'client_payment', 'client', $client, null, 'proofs/c.png'));
        $this->wf->unblock($order, $this->operator());

        $order->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(1, $this->wf->expireStale());
        $this->assertSame('expired', $order->fresh()->status);
    }

    public function test_crypto_est_bientot_disponible(): void
    {
        $client = $this->client();
        $this->fails(fn () => $this->wf->create($client, Corridor::where('code', 'crypto')->first(), 200, $client->payoutMethods()->first()));
    }
}