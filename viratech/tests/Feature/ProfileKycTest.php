<?php

namespace Tests\Feature;

use App\Models\Corridor;
use App\Models\KycSubmission;
use App\Models\Order;
use App\Models\User;
use App\Services\KycService;
use App\Services\LimitPolicy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

class ProfileKycTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Notification::fake();
        Storage::fake();
        config(['viratech.email_code.show_dev_code' => true]);
    }

    private function jpeg(int $seed = 1): UploadedFile
    {
        $im = imagecreatetruecolor(800, 800);
        imagefill($im, 0, 0, imagecolorallocate($im, $seed * 20 % 255, 90, 160));
        imagestring($im, 5, 20, 20, 'photo '.$seed, imagecolorallocate($im, 255, 255, 255));
        ob_start();
        imagejpeg($im);

        return UploadedFile::fake()->createWithContent('p'.$seed.'.jpg', (string) ob_get_clean());
    }

    private function newClient(bool $emailVerified = true, string $email = 'nouveau@x.com'): User
    {
        return User::create(['name' => 'Nouveau Client', 'email' => $email, 'password' => 'motdepasse1', 'role' => 'client', 'email_verified_at' => $emailVerified ? now() : null, 'kyc_level' => $emailVerified ? 1 : 0]);
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

    public function test_verification_de_l_email_par_code_envoye_par_email(): void
    {
        Mail::fake();
        $u = $this->newClient(false);
        $token = $this->postJson('/api/auth/login', ['email' => 'nouveau@x.com', 'password' => 'motdepasse1', 'app' => 'client'])->json('token');
        $h = fn () => ['Authorization' => 'Bearer '.$token];

        $dev = $this->postJson('/api/email/send', [], $h())->assertOk()->json('dev_code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $dev);
        app('auth')->forgetGuards();
        $this->postJson('/api/email/send', [], $h())->assertStatus(422);              // un code par minute
        app('auth')->forgetGuards();
        $this->postJson('/api/email/verify', ['code' => '111111'], $h())->assertStatus(422);
        app('auth')->forgetGuards();
        $this->postJson('/api/email/verify', ['code' => $dev], $h())->assertOk()->assertJsonPath('user.email_verified', true);
        $this->assertSame(150.0, $u->fresh()->monthlyLimit());                           // email vérifié : 150 $
    }

    public function test_l_inscription_envoie_le_code_par_email(): void
    {
        Mail::fake();
        $this->post('/inscription', ['name' => 'Nouveau', 'email' => 'inscrit@x.com', 'password' => 'motdepasse1', 'password_confirmation' => 'motdepasse1'])->assertRedirect('/profil');
        $u = User::where('email', 'inscrit@x.com')->first();
        $this->assertNotNull($u->email_code);
        $this->assertNull($u->email_verified_at);
        $this->assertSame(0.0, $u->monthlyLimit());                                       // rien sans email vérifié
    }

    public function test_plafond_150_sans_identite_3000_avec_et_montee_avec_les_echanges(): void
    {
        $policy = app(LimitPolicy::class);
        $u = $this->newClient(false);
        $this->assertSame(0.0, $policy->limit($u));                                       // email non vérifié

        $u->update(['email_verified_at' => now(), 'kyc_level' => 1]);
        $this->assertSame(150.0, $policy->limit($u->fresh()));                            // email vérifié, identité non vérifiée

        $corridor = Corridor::where('code', 'paypal_equity')->first();
        $make = fn (int $i) => Order::create(['reference' => 'L'.$i, 'user_id' => $u->id, 'corridor_id' => $corridor->id, 'status' => 'completed', 'amount' => 150, 'percent_applied' => 10, 'percent_fee' => 15, 'fixed_fee' => 0, 'total_fee' => 15, 'net_amount' => 135, 'payout_kind' => 'equity', 'payout_account' => '1', 'payout_holder' => 'x']);
        foreach (range(1, 12) as $i) {
            $make($i);
        }
        $this->assertSame(150.0, $policy->limit($u->fresh()));                            // pas de montée sans identité vérifiée

        $u->update(['kyc_level' => 2]);
        $this->assertSame(6000.0, $policy->limit($u->fresh()));                           // identité vérifiée, 12 échanges : 3 000 × 2
        $this->assertSame(13, $policy->describe($u->fresh())['next']['orders_needed']);

        $u->update(['custom_monthly_limit' => 12000]);
        $this->assertSame(12000.0, $policy->limit($u->fresh()));                          // plafond fixé à la main
    }

    public function test_verification_d_identite_en_deux_temps_puis_validation_par_l_equipe(): void
    {
        $u = $this->newClient();
        $kyc = app(KycService::class);
        $this->assertSame('none', $kyc->stage($u));

        // Étape 1 : la pièce. La carte d'électeur exige aussi l'arrière.
        $this->fails(fn () => $kyc->submitDocument($u, 'carte_electeur', $this->jpeg(1)));
        $this->fails(fn () => $kyc->submitDocument($u, 'permis', $this->jpeg(1)));
        $kyc->submitDocument($u, 'carte_electeur', $this->jpeg(1), $this->jpeg(2));
        $this->assertSame('document', $kyc->stage($u->fresh()));

        // Le selfie exige une pièce déjà reçue et un code de sécurité valide.
        $this->fails(fn () => $kyc->submitSelfie($u, $this->jpeg(3)));                    // aucun code demandé
        $challenge = $kyc->challenge($u);
        $this->assertSame($challenge->id, $kyc->challenge($u)->id);                       // même code tant qu'il est valide

        $s = $kyc->submitSelfie($u, $this->jpeg(3));
        $this->assertSame('pending', $s->status);
        $this->assertSame($challenge->code, $s->challenge_code);
        Storage::assertExists($s->selfie_path);
        $this->assertSame('pending', $kyc->stage($u->fresh()));
        $this->assertSame(150.0, $u->fresh()->monthlyLimit());                            // en attente : toujours 150 $

        $kyc->approve($s, User::where('email', 'operateur@viratech.test')->first());
        $this->assertSame(2, (int) $u->fresh()->kyc_level);
        $this->assertSame(3000.0, $u->fresh()->monthlyLimit());
        $this->assertSame('approved', $kyc->stage($u->fresh()));
    }

    public function test_selfie_sans_piece_et_email_non_verifie_sont_refuses(): void
    {
        $kyc = app(KycService::class);
        $u = $this->newClient();
        $kyc->challenge($u);
        $this->fails(fn () => $kyc->submitSelfie($u, $this->jpeg(1)));                    // pas de pièce

        $unverified = $this->newClient(false, 'non@x.com');
        $this->fails(fn () => $kyc->submitDocument($unverified, 'passeport', $this->jpeg(1)));
    }

    public function test_les_photos_reutilisees_par_un_autre_compte_sont_signalees(): void
    {
        $kyc = app(KycService::class);
        $a = $this->newClient();
        $b = $this->newClient(true, 'b@x.com');

        $kyc->submitDocument($a, 'passeport', $this->jpeg(7));
        $kyc->challenge($a);
        $sa = $kyc->submitSelfie($a, $this->jpeg(8));
        $this->assertNull($sa->flags);

        $docB = $kyc->submitDocument($b, 'passeport', $this->jpeg(7));                   // même pièce que le compte A
        $this->assertStringContainsString('autre compte', implode(' ', $docB->flags));
        $kyc->challenge($b);
        $sb = $kyc->submitSelfie($b, $this->jpeg(8));                                      // même selfie que le compte A
        $this->assertGreaterThanOrEqual(2, count($sb->flags));
    }

    public function test_refus_d_un_dossier_garde_la_limite_et_permet_de_recommencer(): void
    {
        $kyc = app(KycService::class);
        $u = $this->newClient();
        $kyc->submitDocument($u, 'passeport', $this->jpeg(1));
        $kyc->challenge($u);
        $s = $kyc->submitSelfie($u, $this->jpeg(2));
        $kyc->reject($s, User::where('email', 'operateur@viratech.test')->first(), 'Photo floue.');

        $this->assertSame('rejected', $kyc->stage($u->fresh()));
        $this->assertSame(1, (int) $u->fresh()->kyc_level);
        $kyc->submitDocument($u->fresh(), 'passeport', $this->jpeg(5));                    // on peut recommencer
        $this->assertSame('document', $kyc->stage($u->fresh()));
    }

    public function test_le_flux_web_envoie_pieces_et_selfie_et_l_admin_voit_le_dossier(): void
    {
        $u = $this->newClient();
        $this->actingAs($u)->get('/profil')->assertOk()->assertSee('Étape 1 sur 2')->assertSee('Ouvrir la caméra');
        $this->actingAs($u)->post('/profil/verification/piece', ['id_type' => 'passeport', 'id_front' => $this->jpeg(1)])->assertSessionHas('ok');
        $page = $this->actingAs($u)->get('/profil')->assertOk()->assertSee('Étape 2 sur 2');
        $code = app(KycService::class)->challenge($u)->code;
        $page->assertSee($code);

        $this->actingAs($u)->post('/profil/verification/selfie', ['selfie' => $this->jpeg(2)])->assertSessionHas('ok');
        $s = KycSubmission::first();
        $this->assertSame('pending', $s->status);

        $this->actingAs($u)->get('/admin/verifications/'.$s->id.'/fichier/selfie')->assertForbidden();
        $op = User::where('email', 'operateur@viratech.test')->first();
        $this->actingAs($op)->get('/admin/verifications/'.$s->id.'/fichier/selfie')->assertOk();
        $this->actingAs($op)->get('/admin/verifications/'.$s->id)->assertOk()->assertSee($code);
        $this->actingAs($op)->post('/admin/verifications/'.$s->id.'/approuver')->assertRedirect('/admin/verifications');
        $this->assertSame(3000.0, $u->fresh()->monthlyLimit());
    }

    public function test_photo_de_profil(): void
    {
        $u = $this->newClient();
        $this->actingAs($u)->post('/profil/photo', ['photo' => $this->jpeg(5)])->assertSessionHas('ok');
        $u->refresh();
        $this->assertNotNull($u->avatar_path);
        Storage::assertExists($u->avatar_path);
        $this->actingAs($u)->get('/avatar/'.$u->id)->assertOk();
        $other = User::where('email', 'client@viratech.test')->first();
        $this->actingAs($other)->get('/avatar/'.$u->id)->assertNotFound();                // un autre client ne voit pas la photo
    }
}