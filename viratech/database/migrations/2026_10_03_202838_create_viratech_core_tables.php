<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Corridors : chaque sens d'échange, avec son minimum, son frais fixe et ses délais. Tout est modifiable par l'admin.
        Schema::create('corridors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();      // paypal_equity, paypal_mobile, mobile_paypal, equity_paypal
            $table->string('label');
            $table->string('source_kind', 20);         // paypal | equity | mobile_money | crypto
            $table->string('target_kind', 20);
            $table->decimal('min_amount', 12, 2);
            $table->decimal('fixed_fee', 12, 2)->default(0);
            $table->unsignedInteger('eta_min_minutes')->default(5);
            $table->unsignedInteger('eta_max_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->boolean('coming_soon')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Paliers de pourcentage : le palier retenu est celui dont le minimum est le plus élevé sans dépasser le montant.
        Schema::create('fee_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corridor_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_amount', 12, 2);
            $table->decimal('percent', 5, 2);
            $table->timestamps();
        });

        // Comptes de réception de l'entreprise (affichés aux clients), modifiables dans le tableau de bord admin.
        Schema::create('company_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);                // paypal | equity | mobile_money
            $table->string('label');
            $table->string('account_value');
            $table->string('holder_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Moyens de réception enregistrés par les clients.
        Schema::create('payout_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);                // equity | mpesa | airtel | orange | afrimoney | paypal
            $table->string('label')->nullable();
            $table->string('account_value');
            $table->string('holder_name');
            $table->boolean('is_verified')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('corridor_id')->constrained();
            $table->string('status', 20)->default('active')->index(); // active | completed | rejected | expired | cancelled
            $table->decimal('amount', 12, 2);
            $table->decimal('percent_applied', 5, 2);
            $table->decimal('percent_fee', 12, 2);
            $table->decimal('fixed_fee', 12, 2);
            $table->decimal('total_fee', 12, 2);
            $table->decimal('net_amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->foreignId('payout_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payout_kind', 20);
            $table->string('payout_account');
            $table->string('payout_holder');
            $table->string('source_kind', 20)->nullable();       // pour les dépôts : mpesa, airtel, orange, equity
            $table->string('deposit_mode', 20)->nullable();      // invoice | account (PayPal)
            $table->string('paypal_invoice_id')->nullable();
            $table->string('paypal_transaction_id')->nullable()->index();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fees_locked_until')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('closed_reason')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        // Étapes réelles : une étape n'est « faite » que lorsqu'un événement réel l'a déclenchée.
        Schema::create('order_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('key', 40);
            $table->string('label');
            $table->string('pending_label');
            $table->string('actor', 10);               // system | client | operator
            $table->string('status', 10)->default('pending'); // pending | done | blocked
            $table->timestamp('started_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->string('done_by_type', 10)->nullable();    // system | client | operator
            $table->foreignId('done_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();        // raison d'un blocage ou référence
            $table->unique(['order_id', 'key']);
            $table->timestamps();
        });

        Schema::create('order_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);                // client_payment | operator_payout
            $table->string('reference')->nullable();
            $table->string('path')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Ledger à double entrée : chaque mouvement = au moins un débit et un crédit de même total.
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction', 36)->index(); // identifiant du mouvement (groupe d'écritures équilibrées)
            $table->string('account', 60)->index();     // ex. company:paypal, client:12:payable, company:fees
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->string('description')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('old')->nullable();
            $table->json('new')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        // Version publiée des applications (système de mise à jour, identique à LeWebPOS).
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('build');
            $table->string('version', 20);
            $table->string('url')->nullable();         // APK client
            $table->string('url_admin')->nullable();   // APK admin
            $table->json('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['app_versions', 'audit_logs', 'ledger_entries', 'order_proofs', 'order_steps', 'orders', 'payout_methods', 'company_accounts', 'fee_tiers', 'corridors'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
