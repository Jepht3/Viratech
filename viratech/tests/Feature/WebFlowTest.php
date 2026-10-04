<?php

namespace Tests\Feature;

use App\Models\Corridor;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebFlowTest extends TestCase
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

    private function as(string $email): static
    {
        return $this->actingAs(User::where('email', $email)->first());
    }

    public function test_les_invites_sont_redirigees_vers_la_connexion(): void
    {
        $this->get('/tableau-de-bord')->assertRedirect('/connexion');
        $this->get('/admin')->assertRedirect('/connexion');
        $this->get('/profil')->assertRedirect('/connexion');
    }

    public function test_un_client_ne_peut_pas_ouvrir_la_console_admin(): void
    {
        $this->as('client@viratech.test')->get('/admin')->assertForbidden();
        $this->as('client@viratech.test')->get('/admin/frais')->assertForbidden();
        $this->as('client@viratech.test')->get('/admin/parametres')->assertForbidden();
        $this->as('client@viratech.test')->get('/admin/verifications')->assertForbidden();
    }

    public function test_un_operateur_ne_peut_pas_modifier_les_frais_ni_les_parametres(): void
    {
        $this->as('operateur@viratech.test')->get('/admin')->assertOk();
        $this->as('operateur@viratech.test')->get('/admin/verifications')->assertOk();
        $this->as('operateur@viratech.test')->get('/admin/frais')->assertForbidden();
        $this->as('operateur@viratech.test')->get('/admin/parametres')->assertForbidden();
    }

    public function test_l_inscription_cree_toujours_un_client_au_telephone_non_verifie(): void
    {
        $this->post('/inscription', ['name' => 'Test', 'email' => 't@x.com', 'phone' => '+243', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1', 'role' => 'admin'])
            ->assertRedirect('/tableau-de-bord');
        $u = User::where('email', 't@x.com')->first();
        $this->assertSame('client', $u->role);
        $this->assertNull($u->phone_verified_at);
        $this->assertSame(0.0, $u->monthlyLimit());
    }

    public function test_parcours_complet_client_et_operateur_avec_preuves(): void
    {
        $client = User::where('email', 'client@viratech.test')->first();
        $method = $client->payoutMethods()->where('kind', 'equity')->first();

        $this->as('client@viratech.test')->get('/echange/nouveau')->assertOk()->assertSee('PayPal vers Equity');
        $this->as('client@viratech.test')->postJson('/echange/devis', ['corridor' => 'paypal_equity', 'amount' => 200])->assertJson(['ok' => true, 'quote' => ['net' => '180.00']]);
        $this->as('client@viratech.test')->postJson('/echange/devis', ['corridor' => 'paypal_equity', 'amount' => 100])->assertJson(['ok' => false]);

        $this->as('client@viratech.test')->post('/echange', ['corridor' => 'paypal_equity', 'amount' => 200, 'payout_method_id' => $method->id, 'payment_method' => 'paypal_account'])->assertRedirect();
        $order = Order::first();
        $ref = $order->reference;
        $this->assertSame('180.00', $order->net_amount);
        $this->as('client@viratech.test')->get('/commandes/'.$ref)->assertOk()->assertSee('capture de la transaction');

        // Sans capture : refusé. Avec capture : l'étape est validée par le client.
        $this->as('client@viratech.test')->post('/commandes/'.$ref.'/preuve', ['reference_code' => 'X'])->assertSessionHasErrors('file');
        $this->as('client@viratech.test')->post('/commandes/'.$ref.'/preuve', ['file' => UploadedFile::fake()->image('paiement.png'), 'reference_code' => 'PP-1'])->assertSessionHas('ok');

        $op = 'operateur@viratech.test';
        $this->as($op)->post('/admin/commandes/'.$ref.'/etape', ['key' => 'payment_verified'])->assertSessionHas('ok');
        // Délai de sécurité : l'opérateur ne peut pas continuer, l'administrateur peut le lever.
        $this->as($op)->post('/admin/commandes/'.$ref.'/etape', ['key' => 'security_check'])->assertSessionHasErrors('reference_code');
        $this->as($op)->post('/admin/commandes/'.$ref.'/lever-delai', ['reason' => 'x'])->assertForbidden();
        $this->as('admin@viratech.test')->post('/admin/commandes/'.$ref.'/lever-delai', ['reason' => 'Client fiable'])->assertSessionHas('ok');

        $this->as($op)->post('/admin/commandes/'.$ref.'/etape', ['key' => 'security_check'])->assertSessionHas('ok');
        $this->as($op)->post('/admin/commandes/'.$ref.'/etape', ['key' => 'payout_in_progress'])->assertSessionHas('ok');
        $this->as($op)->post('/admin/commandes/'.$ref.'/etape', ['key' => 'payout_done', 'reference_code' => 'EQ-778'])->assertSessionHasErrors('reference_code'); // sans capture
        $this->as($op)->post('/admin/commandes/'.$ref.'/etape', ['key' => 'payout_done', 'reference_code' => 'EQ-778', 'file' => UploadedFile::fake()->image('versement.png')])->assertSessionHas('ok');

        $this->assertSame('completed', $order->fresh()->status);
        // Le client voit la capture du versement.
        $this->as('client@viratech.test')->get('/commandes/'.$ref)->assertSee('Preuve du versement');
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
        $client = User::where('email', 'client@viratech.test')->first(); // téléphone vérifié : 500 $ / mois
        $method = $client->payoutMethods()->where('kind', 'equity')->first();

        $this->as('client@viratech.test')->post('/echange', ['corridor' => 'paypal_equity', 'amount' => 600, 'payout_method_id' => $method->id])->assertSessionHasErrors('amount');
        $this->assertSame(0, Order::count());
    }

    public function test_sans_telephone_verifie_on_est_renvoye_vers_le_profil(): void
    {
        $client = User::where('email', 'client@viratech.test')->first();
        $method = $client->payoutMethods()->where('kind', 'equity')->first();
        $client->update(['phone_verified_at' => null]);

        $this->as('client@viratech.test')->post('/echange', ['corridor' => 'paypal_equity', 'amount' => 200, 'payout_method_id' => $method->id])->assertRedirect('/profil');
        $this->assertSame(0, Order::count());
    }

    public function test_les_pages_principales_s_affichent(): void
    {
        foreach (['/tableau-de-bord', '/echange/nouveau', '/commandes', '/moyens-de-reception', '/profil', '/notifications'] as $url) {
            $this->as('client@viratech.test')->get($url)->assertOk();
        }
        foreach (['/admin', '/admin/commandes', '/admin/clients', '/admin/verifications', '/profil'] as $url) {
            $this->as('operateur@viratech.test')->get($url)->assertOk();
        }
        foreach (['/admin/frais', '/admin/comptes', '/admin/audit', '/admin/parametres'] as $url) {
            $this->as('admin@viratech.test')->get($url)->assertOk();
        }
    }

    public function test_l_api_de_version_repond(): void
    {
        $this->getJson('/api/application/version')->assertOk()->assertJsonStructure(['build', 'version', 'url', 'url_admin', 'notes']);
    }
}