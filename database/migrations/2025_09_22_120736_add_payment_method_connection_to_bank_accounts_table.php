<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->string('payment_method_type')->nullable()->after('account_number'); // e.g., 'paystack', 'buni', 'manual'
            $table->json('payment_method_config')->nullable()->after('payment_method_type'); // Store payment method specific configuration
            $table->boolean('is_payment_enabled')->default(false)->after('payment_method_config'); // Enable/disable payment processing
            $table->string('payment_reference')->nullable()->after('is_payment_enabled'); // External payment method reference
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method_type',
                'payment_method_config', 
                'is_payment_enabled',
                'payment_reference'
            ]);
        });
    }
};