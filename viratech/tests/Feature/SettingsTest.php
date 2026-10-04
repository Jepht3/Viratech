<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\FlexpayGateway;
use App\Services\OrderWorkflow;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        config(['viratech.simulate_paypal' => true]);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@viratech.test')->first();
    }

    public function test_les_secrets_sont_chiffres_et_jamais_reaffiches(): void
    {
        $this->actingAs($this->admin())->post('/admin/parametres', [
            'flexpay_enabled' => '1', 'flexpay_environment' => 'sandbox', 'flexpay_merchant' => 'VIRATECH', 'flexpay_token' => 'SECRET-TOKEN-123456',
            'mail_resend_key' => 're_abcdef123456', 'mail_from_address' => 'no-reply@viratech.cd', 'push_fcm_project_id' => 'viratech-123',
        ])->assertSessionHas('ok');

        // En base : chiffré. À l'écran : masqué.
        $raw = DB::table('settings')->where('key', 'flexpay.token')->value('value');
        $this->assertStringNotContainsString('SECRET-TOKEN', (string) $raw);
        $this->assertSame('SECRET-TOKEN-123456', Setting::get('flexpay.token'));
        $page = $this->actingAs($this->admin())->get('/admin/parametres')->assertOk();
        $page->assertDontSee('SECRET-TOKEN-123456');
        $page->assertSee('••••3456');
        $this->assertNotNull(Setting::get('flexpay.callback_secret'));

        // Un champ secret laissé vide conserve la valeur existante.
        $this->actingAs($this->admin())->post('/admin/parametres', ['flexpay_merchant' => 'VIRATECH', 'flexpay_token' => ''])->assertSessionHas('ok');
        $this->assertSame('SECRET-TOKEN-123456', Setting::get('flexpay.token'));
    }

    public function test_l_api_des_parametres_est_reservee_a_l_admin_et_masque_les_secrets(): void
    {
        $tok = $this->postJson('/api/auth/login', ['email' => 'admin@viratech.test', 'password' => 'password', 'app' => 'admin'])->json('token');
        $r = $this->postJson('/api/admin/settings', ['flexpay_merchant' => 'M1', 'flexpay_token' => 'TOK-987654', 'flexpay_enabled' => true], ['Authorization' => 'Bearer '.$tok])->assertOk();
        $this->assertSame('••••7654', $r->json()['flexpay.token']['masked']);
        $this->assertStringNotContainsString('TOK-987654', $r->getContent());
    }

    public function test_le_rappel_flexpay_exige_le_secret_et_verifie_l_etat_reel(): void
    {
        Setting::set('flexpay.callback_secret', 'S3CRET', true);
        $client = User::where('email', 'client@viratech.test')->first();
        $order = app(OrderWorkflow::class)->create($client, \App\Models\Corridor::where('code', 'mobile_paypal')->first(), 100, $client->payoutMethods()->where('kind', 'paypal')->first(), 'mpesa', 'flexpay_mobile');
        app(OrderWorkflow::class)->startFlexpay($order, '+243810000000');

        $this->postJson('/api/webhooks/flexpay?secret=FAUX', ['reference' => $order->reference])->assertForbidden();
        $this->postJson('/api/webhooks/flexpay', ['reference' => $order->reference])->assertForbidden();

        // Secret correct mais FlexPay (local) répond « en attente » : le rappel ne valide rien.
        $this->postJson('/api/webhooks/flexpay?secret=S3CRET', ['reference' => $order->reference, 'status' => '0'])->assertOk();
        $this->assertSame('client_payment', $order->fresh(['steps'])->currentStep()->key);

        // FlexPay répond « payé » : l'étape est validée.
        $this->app->bind(FlexpayGateway::class, fn () => new class implements FlexpayGateway
        {
            public function charge(Order $o, string $m, ?string $p = null): array { return ['reference' => 'X', 'url' => null]; }

            public function payout(Order $o, string $p): array { return ['reference' => 'Y']; }

            public function status(string $r): string { return 'paid'; }
        });
        $this->postJson('/api/webhooks/flexpay?secret=S3CRET', ['reference' => $order->reference])->assertOk();
        $this->assertSame('payment_verified', $order->fresh(['steps'])->currentStep()->key);
    }

    public function test_la_passerelle_flexpay_reelle_utilise_les_bons_formats(): void
    {
        Setting::set('flexpay.enabled', '1');
        Setting::set('flexpay.environment', 'sandbox');
        Setting::set('flexpay.merchant', 'VIRATECH');
        Setting::set('flexpay.token', 'TOK', true);
        Setting::set('flexpay.payout_enabled', '1');
        Setting::set('flexpay.callback_secret', 'S3CRET', true);

        Http::fake([
            'beta-backend.flexpay.cd/api/rest/v1/paymentService' => Http::response(['code' => '0', 'message' => 'ok', 'orderNumber' => 'ORD-1']),
            'beta-backend.flexpay.cd/api/rest/v1/merchantPayOutService' => Http::response(['code' => '0', 'orderNumber' => 'PO-1']),
            'beta-backend.flexpay.cd/api/rest/v1/check/*' => Http::response(['code' => '0', 'transaction' => ['status' => '0']]),
            'beta-cardpayment.flexpay.cd/*' => Http::response(['code' => 0, 'orderNumber' => 'CARD-1', 'url' => 'https://gwvisa.flexpay.cd/checkout/abc']),
        ]);

        $client = User::where('email', 'client@viratech.test')->first();
        $wf = app(OrderWorkflow::class);
        $o = $wf->create($client, \App\Models\Corridor::where('code', 'mobile_paypal')->first(), 100, $client->payoutMethods()->where('kind', 'paypal')->first(), 'mpesa', 'flexpay_mobile');
        $wf->startFlexpay($o, '0810000000');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/paymentService') && $r['phone'] === '243810000000' && $r['type'] === 1 && $r['merchant'] === 'VIRATECH' && $r->hasHeader('Authorization', 'Bearer TOK'));

        $o2 = $wf->create($client, \App\Models\Corridor::where('code', 'equity_paypal')->first(), 100, $client->payoutMethods()->where('kind', 'paypal')->first(), 'equity', 'flexpay_card');
        $o2 = $wf->startFlexpay($o2);
        $this->assertSame('https://gwvisa.flexpay.cd/checkout/abc', $o2->flexpay_url);

        $this->assertSame('ORD-1', $o->fresh()->flexpay_reference);
        $this->assertSame('paid', app(FlexpayGateway::class)->status('ORD-1'));
    }

    public function test_le_push_firebase_est_configurable_et_inactif_sans_reglage(): void
    {
        $fcm = app(\App\Services\FcmClient::class);
        $this->assertFalse($fcm->configured());
        Setting::set('push.fcm_project_id', 'viratech-123');
        Setting::set('push.fcm_service_account', json_encode(['client_email' => 'a@b.iam', 'private_key' => 'x']), true);
        $this->assertTrue($fcm->configured());
    }
}