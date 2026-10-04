<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** L'ancien compte unique « mobile_money » devient un numéro par réseau (valeur reprise, à remplacer par l'admin). */
    public function up(): void
    {
        $old = DB::table('company_accounts')->where('kind', 'mobile_money')->first();
        if (! $old) {
            return;
        }
        foreach (['mpesa' => 'M-Pesa', 'airtel' => 'Airtel Money', 'orange' => 'Orange Money', 'afrimoney' => 'Afrimoney'] as $kind => $label) {
            if (! DB::table('company_accounts')->where('kind', $kind)->exists()) {
                DB::table('company_accounts')->insert([
                    'kind' => $kind, 'label' => $label, 'account_value' => $old->account_value,
                    'holder_name' => $old->holder_name ?? null, 'is_active' => $old->is_active,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        DB::table('company_accounts')->where('id', $old->id)->delete();
    }

    public function down(): void {}
};
