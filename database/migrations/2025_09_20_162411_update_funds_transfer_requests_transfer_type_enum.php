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
        Schema::table('funds_transfer_requests', function (Blueprint $table) {
            $table->dropColumn('transfer_type');
        });
        
        Schema::table('funds_transfer_requests', function (Blueprint $table) {
            $table->enum('transfer_type', ['kcb_buni', 'paystack_mpesa', 'own_account', 'other_account'])->after('debit_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funds_transfer_requests', function (Blueprint $table) {
            $table->dropColumn('transfer_type');
        });
        
        Schema::table('funds_transfer_requests', function (Blueprint $table) {
            $table->enum('transfer_type', ['kcb_buni', 'paystack_mpesa'])->after('debit_account_id');
        });
    }
};
