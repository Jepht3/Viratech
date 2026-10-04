<?php

namespace Database\Seeders;

use App\Models\AppVersion;
use App\Models\CompanyAccount;
use App\Models\Corridor;
use App\Models\PayoutMethod;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->corridors();
        $this->companyAccounts();
        $this->users();

        AppVersion::firstOrCreate(['build' => 1], [
            'version' => '1.0.0',
            'notes' => ['Première version de Viratech.'],
        ]);
    }

    /**
     * Barème décidé : voir docs/CAHIER_DES_CHARGES.md §4.5. Tout est modifiable dans l'admin.
     * Paiements qui arrivent par mobile money, Equity ou carte : traités rapidement. Paiements PayPal : plus lents (délai de sécurité
     * de 7 jours par défaut, ×2 pour un client non vérifié, ÷2 pour un client très fiable) pour éviter les rétrofacturations.
     */
    private function corridors(): void
    {
        $withdrawTiers = [[150, 10], [500, 7], [2001, 6]];
        $day = 1440;
        $defs = [
            // code, libellé, source, cible, minimum, frais fixe, délai min, délai max (minutes), délai de sécurité (minutes), paliers, tri
            ['paypal_equity', 'PayPal vers Equity', 'paypal', 'equity', 150, 0, 3 * $day, 14 * $day, 7 * $day, $withdrawTiers, 1],
            ['paypal_mobile', 'PayPal vers mobile money', 'paypal', 'mobile_money', 150, 2, 3 * $day, 14 * $day, 7 * $day, $withdrawTiers, 2],
            ['mobile_paypal', 'Mobile money vers PayPal', 'mobile_money', 'paypal', 100, 2, 5, 30, 0, [[100, 10]], 3],
            ['equity_paypal', 'Equity vers PayPal', 'equity', 'paypal', 100, 0, 5, 30, 0, [[100, 10]], 4],
        ];

        foreach ($defs as [$code, $label, $from, $to, $min, $fixed, $etaMin, $etaMax, $hold, $tiers, $sort]) {
            $c = Corridor::updateOrCreate(['code' => $code], [
                'label' => $label, 'source_kind' => $from, 'target_kind' => $to, 'min_amount' => $min,
                'fixed_fee' => $fixed, 'eta_min_minutes' => $etaMin, 'eta_max_minutes' => $etaMax, 'hold_minutes' => $hold, 'sort' => $sort,
            ]);
            $c->tiers()->delete();
            foreach ($tiers as [$tierMin, $pct]) {
                $c->tiers()->create(['min_amount' => $tierMin, 'percent' => $pct]);
            }
        }

        Corridor::updateOrCreate(['code' => 'crypto'], [
            'label' => 'Crypto (USDT)', 'source_kind' => 'crypto', 'target_kind' => 'crypto', 'min_amount' => 0,
            'is_active' => false, 'coming_soon' => true, 'sort' => 9,
        ]);
    }
    /** Valeurs provisoires fournies par le propriétaire, à modifier dans l'admin. */
    private function companyAccounts(): void
    {
        CompanyAccount::firstOrCreate(['kind' => 'paypal'], ['label' => 'PayPal Business', 'account_value' => 'jepht3@gmail.com']);
        CompanyAccount::firstOrCreate(['kind' => 'equity'], ['label' => 'Equity', 'account_value' => '0000000000000000000000']);
        CompanyAccount::firstOrCreate(['kind' => 'mobile_money'], ['label' => 'Mobile money', 'account_value' => '0000000000000']);
    }

    private function users(): void
    {
        // Comptes de test locaux (mot de passe : password). À supprimer / changer avant toute mise en ligne.
        User::firstOrCreate(['email' => 'admin@viratech.test'], ['name' => 'Administrateur', 'role' => 'admin', 'password' => 'password', 'phone' => '+243000000001', 'kyc_level' => 3, 'email_verified_at' => now()]);
        User::firstOrCreate(['email' => 'operateur@viratech.test'], ['name' => 'Opérateur', 'role' => 'operator', 'password' => 'password', 'phone' => '+243000000002', 'kyc_level' => 3, 'email_verified_at' => now()]);

        $client = User::firstOrCreate(['email' => 'client@viratech.test'], ['name' => 'Jean Freelance', 'role' => 'client', 'password' => 'password', 'phone' => '+243810000000', 'kyc_level' => 2, 'email_verified_at' => now()]);
        foreach ([$client, $this->beginner()] as $c) {
            $c->payoutMethods()->firstOrCreate(['kind' => 'equity'], ['label' => 'Mon compte Equity', 'account_value' => '1234567890123456', 'holder_name' => $c->name, 'is_verified' => true]);
            $c->payoutMethods()->firstOrCreate(['kind' => 'mpesa'], ['label' => 'M-Pesa', 'account_value' => '+243810000000', 'holder_name' => $c->name, 'is_verified' => true]);
            $c->payoutMethods()->firstOrCreate(['kind' => 'paypal'], ['label' => 'Mon PayPal', 'account_value' => 'client@example.com', 'holder_name' => $c->name, 'is_verified' => true]);
        }
    }

    /** Client dont l'email est vérifié mais pas l'identité : limité à 150 $ par mois. */
    private function beginner(): User
    {
        return User::firstOrCreate(['email' => 'debutant@viratech.test'], ['name' => 'Marie Débutante', 'role' => 'client', 'password' => 'password', 'kyc_level' => 1, 'email_verified_at' => now()]);
    }
}