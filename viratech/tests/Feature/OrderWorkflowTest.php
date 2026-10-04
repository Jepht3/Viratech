<?php

namespace Tests\Feature;

use App\Models\Corridor;
use App\Models\LedgerEntry;
use App\Models\User;
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

    public function test_retrait_paypal_vers_mobile_money_suit_les_etapes_reelles(): void
    {
        $client = $this->client();
        $method = $client->payoutMethods()->where('kind', 'mpesa')->first();
        $order = $this->wf->create($client, Corridor::where('code', 'paypal_mobile')->first(), 200, $method);

        $this->assertSame('178.00', $order->net_amount);
        $this->assertSame('payment_received', $order->currentStep()->key);
        $this->assertSame(['created'], $order->steps->where('status', 'done')->pluck('key')->all());

        // Le versement ne peut pas être validé avant le paiement PayPal.
        try {
            $this->wf->complete($order, 'payout_done', 'operator', $this->operator(), 'REF1');
            $this->fail('Étape hors ordre acceptée');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $order = $this->wf->complete($order, 'payment_received', 'system');
        $order = $this->wf->complete($order, 'security_check', 'operator', $this->operator());
        $order = $this->wf->complete($order, 'payout_in_progress', 'operator', $this->operator());

        // Un client ne peut pas valider une étape opérateur, et la preuve de versement est obligatoire.
        try {
            $this->wf->complete($order, 'payout_done', 'client', $client, 'x');
            $this->fail('Le client a validé une étape opérateur');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }
        try {
            $this->wf->complete($order, 'payout_done', 'operator', $this->operator());
            $this->fail('Versement validé sans preuve');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $order = $this->wf->complete($order, 'payout_done', 'operator', $this->operator(), 'MP240001');

        $this->assertSame('completed', $order->status);
        $this->assertNotNull($order->completed_at);
        $this->assertCount(6, $order->steps->where('status', 'done'));

        // Le ledger est équilibré : 200 reçus, 178 versés, 22 de frais.
        $this->assertEquals(LedgerEntry::sum('debit'), LedgerEntry::sum('credit'));
        $this->assertSame('22.00', number_format((float) LedgerEntry::where('account', 'company:fees')->sum('credit'), 2, '.', ''));
    }

    public function test_depot_mobile_vers_paypal_exige_la_preuve_du_client(): void
    {
        $client = $this->client();
        $method = $client->payoutMethods()->where('kind', 'paypal')->first();
        $order = $this->wf->create($client, Corridor::where('code', 'mobile_paypal')->first(), 100, $method, 'mpesa');

        $this->assertSame('88.00', $order->net_amount);
        $this->assertSame('deposit_proof', $order->currentStep()->key);

        $this->expectException(InvalidArgumentException::class);
        $this->wf->complete($order, 'deposit_proof', 'client', $client); // sans référence
    }

    public function test_blocage_et_refus(): void
    {
        $client = $this->client();
        $method = $client->payoutMethods()->where('kind', 'equity')->first();
        $order = $this->wf->create($client, Corridor::where('code', 'paypal_equity')->first(), 300, $method);

        $this->wf->block($order, $this->operator(), 'Capture illisible, merci de renvoyer.');
        $order->refresh()->load('steps');
        $this->assertSame('blocked', $order->currentStep()->status);

        try {
            $this->wf->complete($order, 'payment_received', 'system');
            $this->fail('Étape bloquée validée');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->wf->reject($order, $this->operator(), 'Paiement jamais reçu.');
        $this->assertSame('rejected', $order->fresh()->status);
    }

    public function test_crypto_est_bientot_disponible(): void
    {
        $client = $this->client();
        $method = $client->payoutMethods()->first();
        $this->expectException(InvalidArgumentException::class);
        $this->wf->create($client, Corridor::where('code', 'crypto')->first(), 200, $method);
    }

    public function test_commande_expire_sans_action(): void
    {
        $client = $this->client();
        $method = $client->payoutMethods()->where('kind', 'equity')->first();
        $order = $this->wf->create($client, Corridor::where('code', 'paypal_equity')->first(), 200, $method);
        $order->update(['expires_at' => now()->subMinute()]);

        $this->assertSame(1, $this->wf->expireStale());
        $this->assertSame('expired', $order->fresh()->status);
    }
}
