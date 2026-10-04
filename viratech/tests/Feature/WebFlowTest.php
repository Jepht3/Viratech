<?php

namespace Tests\Feature;

use App\Models\Corridor;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WebFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        config(['viratech.simulate_paypal' => true]);
    }

    private function as(string $email): static
    {
        return $this->actingAs(User::where('email', $email)->first());
    }

    public function test_les_invites_sont_redirigees_vers_la_connexion(): void
    {
        $this->get('/tableau-de-bord')->assertRedirect('/connexion');
        $this->get('/admin')->assertRedirect('/connexion');
    }

    public function test_un_client_ne_peut_pas_ouvrir_la_console_admin(): void
    {
        $this->as('client@viratech.test')->get('/admin')->assertForbidden();
        $this->as('client@viratech.test')->get('/admin/frais')->assertForbidden();
    }

    public function test_un_operateur_ne_peut_pas_modifier_les_frais(): void
    {
        $this->as('operateur@viratech.test')->get('/admin')->assertOk();
        $this->as('operateur@viratech.test')->get('/admin/frais')->assertForbidden();
    }

    public function test_l_inscription_cree_toujours_un_client(): void
    {
        $this->post('/inscription', ['name' => 'Test', 'email' => 't@x.com', 'phone' => '+243', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1', 'role' => 'admin'])
            ->assertRedirect('/tableau-de-bord');
        $this->assertSame('client', User::where('email', 't@x.com')->first()->role);
    }

    public function test_parcours_complet_client_et_operateur(): void
    {
        $client = User::where('email', 'client@viratech.test')->first();
        $method = $client->payoutMethods()->where('kind', 'equity')->first();

        $this->as('client@viratech.test')->get('/echange/nouveau')->assertOk()->assertSee('PayPal vers Equity');
        $this->as('client@viratech.test')->postJson('/echange/devis', ['corridor' => 'paypal_equity', 'amount' => 200])
            ->assertJson(['ok' => true, 'quote' => ['net' => '180.00']]);
        $this->as('client@viratech.test')->postJson('/echange/devis', ['corridor' => 'paypal_equity', 'amount' => 100])
            ->assertJson(['ok' => false]);

        $this->as('client@viratech.test')->post('/echange', ['corridor' => 'paypal_equity', 'amount' => 200, 'payout_method_id' => $method->id, 'deposit_mode' => 'invoice'])
            ->assertRedirect();
        $order = Order::first();
        $this->assertSame('180.00', $order->net_amount);

        $this->as('client@viratech.test')->get('/commandes/'.$order->reference)->assertOk()->assertSee('180,00');
        $this->as('client@viratech.test')->post('/commandes/'.$order->reference.'/simuler-paiement')->assertSessionHas('ok');

        $op = 'operateur@viratech.test';
        $this->as($op)->post('/admin/commandes/'.$order->reference.'/etape', ['key' => 'security_check'])->assertSessionHas('ok');
        $this->as($op)->post('/admin/commandes/'.$order->reference.'/etape', ['key' => 'payout_in_progress'])->assertSessionHas('ok');
        $this->as($op)->post('/admin/commandes/'.$order->reference.'/etape', ['key' => 'payout_done'])->assertSessionHasErrors('reference_code');
        $this->as($op)->post('/admin/commandes/'.$order->reference.'/etape', ['key' => 'payout_done', 'reference_code' => 'EQ-778'])->assertSessionHas('ok');

        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_un_client_ne_voit_pas_la_commande_d_un_autre(): void
    {
        $client = User::where('email', 'client@viratech.test')->first();
        $order = app(\App\Services\OrderWorkflow::class)->create($client, Corridor::where('code', 'paypal_equity')->first(), 200, $client->payoutMethods()->where('kind', 'equity')->first());

        $other = User::create(['name' => 'Autre', 'email' => 'autre@x.com', 'password' => 'motdepasse1', 'role' => 'client']);
        $this->actingAs($other)->get('/commandes/'.$order->reference)->assertNotFound();
    }

    public function test_le_plafond_mensuel_est_applique(): void
    {
        $client = User::where('email', 'client@viratech.test')->first(); // N1 : 500 $ / mois
        $method = $client->payoutMethods()->where('kind', 'equity')->first();

        $this->as('client@viratech.test')->post('/echange', ['corridor' => 'paypal_equity', 'amount' => 600, 'payout_method_id' => $method->id])
            ->assertSessionHasErrors('amount');
        $this->assertSame(0, Order::count());
    }

    public function test_l_api_de_version_repond(): void
    {
        $this->getJson('/api/application/version')->assertOk()->assertJsonStructure(['build', 'version', 'url', 'url_admin', 'notes']);
    }
}
