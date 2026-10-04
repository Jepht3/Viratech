<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        Storage::fake();
        config(['viratech.simulate_paypal' => true]);
    }

    private function token(string $email, string $app): string
    {
        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'password', 'app' => $app])->assertOk()->json('token');
    }

    private function h(string $token): array
    {
        app('auth')->forgetGuards(); // en test, le garde mémorise l'utilisateur de la requête précédente

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_chaque_application_n_accepte_que_son_public(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'client@viratech.test', 'password' => 'password', 'app' => 'admin'])->assertForbidden();
        $this->postJson('/api/auth/login', ['email' => 'admin@viratech.test', 'password' => 'password', 'app' => 'client'])->assertForbidden();
        $this->postJson('/api/auth/login', ['email' => 'client@viratech.test', 'password' => 'mauvais', 'app' => 'client'])->assertStatus(422);
    }

    public function test_un_jeton_client_ne_donne_pas_acces_a_l_admin(): void
    {
        $t = $this->token('client@viratech.test', 'client');
        $this->getJson('/api/admin/queue', $this->h($t))->assertForbidden();
        $this->getJson('/api/admin/settings', $this->h($t))->assertForbidden();
        $this->getJson('/api/admin/kyc', $this->h($t))->assertForbidden();
        $this->getJson('/api/dashboard', $this->h($t))->assertOk()->assertJsonStructure(['total_exchanged', 'completed_count']);
    }

    public function test_un_operateur_ne_peut_pas_modifier_les_frais_ni_les_parametres(): void
    {
        $t = $this->token('operateur@viratech.test', 'admin');
        $this->getJson('/api/admin/queue', $this->h($t))->assertOk();
        $this->getJson('/api/admin/kyc', $this->h($t))->assertOk();
        $this->getJson('/api/admin/fees', $this->h($t))->assertForbidden();
        $this->getJson('/api/admin/settings', $this->h($t))->assertForbidden();
        $this->getJson('/api/dashboard', $this->h($t))->assertForbidden();
    }

    public function test_parcours_complet_par_l_api_avec_capture_obligatoire(): void
    {
        $c = $this->token('client@viratech.test', 'client');
        $client = User::where('email', 'client@viratech.test')->first();
        $method = $client->payoutMethods()->where('kind', 'mpesa')->first();

        $this->postJson('/api/quote', ['corridor' => 'paypal_mobile', 'amount' => 200], $this->h($c))->assertJson(['ok' => true, 'quote' => ['net' => '178.00']]);

        $ref = $this->postJson('/api/orders', ['corridor' => 'paypal_mobile', 'amount' => 200, 'payout_method_id' => $method->id, 'payment_method' => 'paypal_account'], $this->h($c))
            ->assertCreated()->assertJsonPath('net_amount', 178)->assertJsonPath('steps.1.status', 'current')->assertJsonPath('instructions.proof_required', true)->json('reference');

        // Capture obligatoire.
        $this->postJson("/api/orders/$ref/proof", ['reference_code' => 'X'], $this->h($c))->assertStatus(422);
        $this->post("/api/orders/$ref/proof", ['file' => UploadedFile::fake()->image('p.png')], $this->h($c) + ['Accept' => 'application/json'])->assertOk()->assertJsonPath('steps.1.status', 'done');

        $a = $this->token('operateur@viratech.test', 'admin');
        $this->postJson("/api/admin/orders/$ref/step", ['key' => 'payment_verified'], $this->h($a))->assertOk()->assertJsonPath('hold_active', true);
        $this->postJson("/api/admin/orders/$ref/step", ['key' => 'security_check'], $this->h($a))->assertStatus(422);

        $admin = $this->token('admin@viratech.test', 'admin');
        $this->postJson("/api/admin/orders/$ref/release-hold", ['reason' => 'Client de confiance'], $this->h($admin))->assertOk()->assertJsonPath('hold_active', false);
        $this->postJson("/api/admin/orders/$ref/step", ['key' => 'security_check'], $this->h($a))->assertOk();
        $this->postJson("/api/admin/orders/$ref/step", ['key' => 'payout_in_progress'], $this->h($a))->assertOk();
        $this->postJson("/api/admin/orders/$ref/step", ['key' => 'payout_done', 'reference_code' => 'MP-1'], $this->h($a))->assertStatus(422);
        $this->post("/api/admin/orders/$ref/step", ['key' => 'payout_done', 'reference_code' => 'MP-1', 'file' => UploadedFile::fake()->image('v.png')], $this->h($a) + ['Accept' => 'application/json'])->assertOk()->assertJsonPath('status', 'completed');

        $this->assertSame('completed', Order::where('reference', $ref)->first()->status);
        $this->getJson('/api/notifications', $this->h($c))->assertOk();
    }

    public function test_paiement_flexpay_simule_par_l_api(): void
    {
        $c = $this->token('client@viratech.test', 'client');
        $client = User::where('email', 'client@viratech.test')->first();
        $pp = $client->payoutMethods()->where('kind', 'paypal')->first();

        $ref = $this->postJson('/api/orders', ['corridor' => 'mobile_paypal', 'amount' => 100, 'payout_method_id' => $pp->id, 'source_kind' => 'airtel', 'payment_method' => 'flexpay_mobile'], $this->h($c))
            ->assertCreated()->assertJsonPath('instructions.type', 'flexpay')->assertJsonPath('can_simulate_payment', true)->json('reference');

        $this->postJson("/api/orders/$ref/flexpay", ['phone' => '+243810000000'], $this->h($c))->assertOk()->assertJsonPath('instructions.started', true);
        $this->postJson("/api/orders/$ref/simulate-payment", [], $this->h($c))->assertOk()->assertJsonPath('steps.1.status', 'done');
    }

    public function test_l_admin_modifie_le_bareme_et_le_delai_de_securite(): void
    {
        $t = $this->token('admin@viratech.test', 'admin');
        $this->getJson('/api/admin/fees', $this->h($t))->assertOk()->assertJsonCount(5);
        $this->postJson('/api/admin/fees/1', ['min_amount' => 150, 'fixed_fee' => 0, 'eta_min_minutes' => 30, 'eta_max_minutes' => 240, 'hold_minutes' => 4320, 'tiers' => [['min_amount' => 150, 'percent' => 9]]], $this->h($t))
            ->assertOk()->assertJsonPath('tiers.0.percent', 9)->assertJsonPath('hold_minutes', 4320);
    }
}