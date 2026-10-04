<?php

namespace Tests\Feature;

use App\Models\KycSubmission;
use App\Models\User;
use App\Services\KycService;
use App\Services\LimitPolicy;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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
        config(['viratech.phone.show_dev_code' => true]);
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

    private function newClient(): User
    {
        return User::create(['name' => 'Nouveau Client', 'email' => 'nouveau@x.com', 'password' => 'motdepasse1', 'role' => 'client', 'phone' => '+243970000000']);
    }

    public function test_verification_du_telephone_par_code(): void
    {
        $u = $this->newClient();
        $this->actingAs($u);

        $code = $this->postJson('/profil/telephone/code')->assertRedirect()->baseResponse; // web : redirection
        $u->refresh();
        $this->assertNotNull($u->phone_code);

        // Mauvais code, puis le bon (récupéré via l'API en mode test).
        $this->post('/profil/telephone/verifier', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertNull($u->fresh()->phone_verified_at);
    }

    public function test_telephone_api_envoie_et_verifie(): void
    {
        $u = $this->newClient();
        $token = $this->postJson('/api/auth/login', ['email' => 'nouveau@x.com', 'password' => 'motdepasse1', 'app' => 'client'])->json('token');
        $h = fn () => ['Authorization' => 'Bearer '.$token];

        $dev = $this->postJson('/api/phone/send', [], $h())->assertOk()->json('dev_code');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $dev);
        app('auth')->forgetGuards();
        $this->postJson('/api/phone/verify', ['code' => '111111'], $h())->assertStatus(422);
        app('auth')->forgetGuards();
        $this->postJson('/api/phone/verify', ['code' => $dev], $h())->assertOk()->assertJsonPath('user.phone_verified', true);
        $this->assertSame(500.0, $u->fresh()->monthlyLimit());
    }

    public function test_plafond_selon_niveau_et_montee_avec_les_echanges_reussis(): void
    {
        $policy = app(LimitPolicy::class);
        $u = $this->newClient();
        $this->assertSame(0.0, $policy->limit($u));                                   // téléphone non vérifié

        $u->update(['phone_verified_at' => now(), 'kyc_level' => 1]);
        $this->assertSame(500.0, $policy->limit($u->fresh()));                        // téléphone vérifié

        $u->update(['kyc_level' => 2]);
        $this->assertSame(3000.0, $policy->limit($u->fresh()));                       // identité vérifiée

        $corridor = \App\Models\Corridor::where('code', 'paypal_equity')->first();
        foreach (range(1, 3) as $i) {
            \App\Models\Order::create(['reference' => 'L'.$i, 'user_id' => $u->id, 'corridor_id' => $corridor->id, 'status' => 'completed', 'amount' => 150, 'percent_applied' => 10, 'percent_fee' => 15, 'fixed_fee' => 0, 'total_fee' => 15, 'net_amount' => 135, 'payout_kind' => 'equity', 'payout_account' => '1', 'payout_holder' => 'x']);
        }
        $this->assertSame(4500.0, $policy->limit($u->fresh()));                       // 3 échanges réussis : ×1,5
        $this->assertSame(7, $policy->describe($u->fresh())['next']['orders_needed']);

        $u->update(['custom_monthly_limit' => 12000]);
        $this->assertSame(12000.0, $policy->limit($u->fresh()));                      // plafond fixé à la main
    }

    public function test_dossier_d_identite_approuve_par_l_equipe(): void
    {
        $u = $this->newClient();
        $u->update(['phone_verified_at' => now(), 'kyc_level' => 1]);
        $kyc = app(KycService::class);

        $challenge = $kyc->challenge($u);
        $this->assertSame($challenge->id, $kyc->challenge($u)->id);                  // même code tant qu'il est valide

        $s = $kyc->submit($u, 'carte_electeur', $this->jpeg(1), $this->jpeg(2));
        $this->assertSame('pending', $s->status);
        $this->assertSame($challenge->code, $s->challenge_code);
        Storage::assertExists($s->selfie_path);

        // Un second dossier en attente est refusé, et le code est consommé.
        $this->expectException(\InvalidArgumentException::class);
        try {
            $kyc->submit($u, 'carte_electeur', $this->jpeg(3), $this->jpeg(4));
        } finally {
            $kyc->approve($s, User::where('email', 'operateur@viratech.test')->first());
            $this->assertSame(2, (int) $u->fresh()->kyc_level);
            $this->assertSame(3000.0, $u->fresh()->monthlyLimit());
        }
    }

    public function test_dossier_d_identite_exige_telephone_verifie_et_code_valide(): void
    {
        $u = $this->newClient();
        $kyc = app(KycService::class);
        try {
            $kyc->submit($u, 'passeport', $this->jpeg(1), $this->jpeg(2));
            $this->fail('Téléphone non vérifié accepté');
        } catch (\InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $u->update(['phone_verified_at' => now(), 'kyc_level' => 1]);
        $this->expectException(\InvalidArgumentException::class); // pas de code de sécurité demandé
        $kyc->submit($u->fresh(), 'passeport', $this->jpeg(1), $this->jpeg(2));
    }

    public function test_les_photos_reutilisees_par_un_autre_compte_sont_signalees(): void
    {
        $kyc = app(KycService::class);
        $a = $this->newClient();
        $a->update(['phone_verified_at' => now(), 'kyc_level' => 1]);
        $b = User::create(['name' => 'Autre', 'email' => 'b@x.com', 'password' => 'motdepasse1', 'role' => 'client', 'phone' => '+243971111111', 'phone_verified_at' => now(), 'kyc_level' => 1]);

        $kyc->challenge($a);
        $sa = $kyc->submit($a, 'carte_electeur', $this->jpeg(7), $this->jpeg(8));
        $this->assertNull($sa->flags);

        $kyc->challenge($b);
        $sb = $kyc->submit($b, 'carte_electeur', $this->jpeg(7), $this->jpeg(9));      // même selfie que le compte A
        $this->assertNotEmpty($sb->flags);
        $this->assertStringContainsString('autre compte', implode(' ', $sb->flags));
    }

    public function test_refus_d_un_dossier_prevenir_le_client(): void
    {
        $u = $this->newClient();
        $u->update(['phone_verified_at' => now(), 'kyc_level' => 1]);
        $kyc = app(KycService::class);
        $kyc->challenge($u);
        $s = $kyc->submit($u, 'permis', $this->jpeg(1), $this->jpeg(2));
        $kyc->reject($s, User::where('email', 'operateur@viratech.test')->first(), 'Photo floue.');
        $this->assertSame('rejected', $s->fresh()->status);
        $this->assertSame(1, (int) $u->fresh()->kyc_level);
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
        $this->actingAs($other)->get('/avatar/'.$u->id)->assertNotFound();           // un autre client ne voit pas la photo
    }

    public function test_les_fichiers_d_identite_ne_sont_visibles_que_par_l_equipe(): void
    {
        $u = $this->newClient();
        $u->update(['phone_verified_at' => now(), 'kyc_level' => 1]);
        $kyc = app(KycService::class);
        $kyc->challenge($u);
        $s = $kyc->submit($u, 'permis', $this->jpeg(1), $this->jpeg(2));

        $this->actingAs($u)->get('/admin/verifications/'.$s->id.'/fichier/selfie')->assertForbidden();
        $this->actingAs(User::where('email', 'operateur@viratech.test')->first())->get('/admin/verifications/'.$s->id.'/fichier/selfie')->assertOk();
        $this->actingAs(User::where('email', 'operateur@viratech.test')->first())->get('/admin/verifications/'.$s->id)->assertOk()->assertSee($s->challenge_code);
    }
}