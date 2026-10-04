<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('phone');
            $table->timestamp('phone_verified_at')->nullable()->after('avatar_path');
            $table->string('phone_code')->nullable();                      // code SMS haché
            $table->timestamp('phone_code_expires_at')->nullable();
            $table->unsignedTinyInteger('phone_code_attempts')->default(0);
            $table->decimal('custom_monthly_limit', 12, 2)->nullable();    // plafond fixé à la main par l'admin (prioritaire)
        });

        Schema::table('orders', function (Blueprint $table) {
            // paypal_invoice | paypal_account | transfer | flexpay_mobile | flexpay_card
            $table->string('payment_method', 20)->nullable()->after('deposit_mode');
            $table->string('flexpay_reference')->nullable()->after('payment_method');
            $table->string('flexpay_url', 500)->nullable()->after('flexpay_reference');
        });


        // Délai de sécurité (anti-rétrofacturation) : les paiements PayPal restent bloqués X minutes avant tout versement.
        Schema::table('corridors', function (Blueprint $table) {
            $table->unsignedInteger('hold_minutes')->default(0)->after('eta_max_minutes');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('payout_not_before')->nullable()->after('expires_at');
            $table->string('payout_via', 12)->nullable();                      // manual | flexpay
            $table->string('flexpay_payout_reference')->nullable();
        });

        // Paramètres modifiables par l'administrateur (FlexPay, emails, notifications push) ; les secrets sont chiffrés.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();
        });

        // Jetons des téléphones pour les notifications push (Firebase Cloud Messaging).
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token', 500);
            $table->string('platform', 12)->default('android');
            $table->string('app', 10)->default('client');
            $table->timestamps();
            $table->unique('token');
        });

        // Vérification d'identité : selfie avec la pièce en main + photo de la pièce, contrôlées par l'équipe.
        Schema::create('kyc_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 12)->default('pending');              // pending | approved | rejected
            $table->string('id_type', 20);                                 // carte_electeur | passeport | permis | carte_identite
            $table->string('challenge_code', 8);                           // code à écrire sur papier et tenir sur le selfie
            $table->string('selfie_path');
            $table->string('id_front_path');
            $table->string('id_back_path')->nullable();
            $table->string('selfie_hash', 64);
            $table->string('id_front_hash', 64);
            $table->string('id_back_hash', 64)->nullable();
            $table->json('flags')->nullable();                             // alertes automatiques (doublon, photo ancienne, etc.)
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->unsignedTinyInteger('granted_level')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index('selfie_hash');
            $table->index('id_front_hash');
        });

        // Code éphémère remis au client avant la prise de photo (anti-réutilisation d'anciennes photos).
        Schema::create('kyc_challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code', 8);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('kyc_challenges');
        Schema::dropIfExists('kyc_submissions');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['payment_method', 'flexpay_reference', 'flexpay_url']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['avatar_path', 'phone_verified_at', 'phone_code', 'phone_code_expires_at', 'phone_code_attempts', 'custom_monthly_limit']));
    }
};
