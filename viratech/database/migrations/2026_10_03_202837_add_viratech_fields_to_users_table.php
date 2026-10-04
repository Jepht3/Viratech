<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('client')->after('email')->index(); // client | operator | admin
            $table->string('phone', 30)->nullable()->after('role');
            $table->unsignedTinyInteger('kyc_level')->default(0)->after('phone');
            $table->boolean('is_active')->default(true)->after('kyc_level');
            $table->boolean('notify_email')->default(true)->after('is_active');
            $table->boolean('notify_push')->default(true)->after('notify_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'kyc_level', 'is_active', 'notify_email', 'notify_push']);
        });
    }
};
