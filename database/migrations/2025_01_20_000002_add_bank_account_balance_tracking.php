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
            $table->decimal('current_balance', 15, 2)->default(0)->after('opening_balance');
            $table->decimal('blocked_balance', 15, 2)->default(0)->after('current_balance');
            $table->timestamp('last_balance_update')->nullable()->after('blocked_balance');
            $table->boolean('is_active')->default(true)->after('last_balance_update');
            $table->boolean('allow_negative_balance')->default(false)->after('is_active');
            $table->decimal('minimum_balance', 15, 2)->default(0)->after('allow_negative_balance');
            $table->decimal('maximum_balance', 15, 2)->nullable()->after('minimum_balance');
        });

        // Add check constraint for balance validation
        DB::statement('ALTER TABLE bank_accounts ADD CONSTRAINT chk_bank_account_balance 
            CHECK (current_balance >= CASE WHEN allow_negative_balance = 0 THEN minimum_balance ELSE -999999999.99 END)');
        
        DB::statement('ALTER TABLE bank_accounts ADD CONSTRAINT chk_bank_account_max_balance 
            CHECK (maximum_balance IS NULL OR current_balance <= maximum_balance)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'current_balance',
                'blocked_balance', 
                'last_balance_update',
                'is_active',
                'allow_negative_balance',
                'minimum_balance',
                'maximum_balance'
            ]);
        });

        DB::statement('ALTER TABLE bank_accounts DROP CONSTRAINT chk_bank_account_balance');
        DB::statement('ALTER TABLE bank_accounts DROP CONSTRAINT chk_bank_account_max_balance');
    }
};
