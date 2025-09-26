<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('bank_accounts')) {
            Schema::table('bank_accounts', function (Blueprint $table) {
                if (!Schema::hasColumn('bank_accounts', 'current_balance')) {
                    $table->decimal('current_balance', 15, 2)->default(0)->after('opening_balance');
                }
                if (!Schema::hasColumn('bank_accounts', 'blocked_balance')) {
                    $table->decimal('blocked_balance', 15, 2)->default(0)->after('current_balance');
                }
                if (!Schema::hasColumn('bank_accounts', 'last_balance_update')) {
                    $table->timestamp('last_balance_update')->nullable()->after('blocked_balance');
                }
                if (!Schema::hasColumn('bank_accounts', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('last_balance_update');
                }
                if (!Schema::hasColumn('bank_accounts', 'allow_negative_balance')) {
                    $table->boolean('allow_negative_balance')->default(false)->after('is_active');
                }
                if (!Schema::hasColumn('bank_accounts', 'minimum_balance')) {
                    $table->decimal('minimum_balance', 15, 2)->default(0)->after('allow_negative_balance');
                }
                if (!Schema::hasColumn('bank_accounts', 'maximum_balance')) {
                    $table->decimal('maximum_balance', 15, 2)->nullable()->after('minimum_balance');
                }
            });

            // Add check constraint for balance validation
            try {
                DB::statement('ALTER TABLE bank_accounts ADD CONSTRAINT chk_bank_account_balance 
                    CHECK (current_balance >= CASE WHEN allow_negative_balance = 0 THEN minimum_balance ELSE -999999999.99 END)');
                
                DB::statement('ALTER TABLE bank_accounts ADD CONSTRAINT chk_bank_account_max_balance 
                    CHECK (maximum_balance IS NULL OR current_balance <= maximum_balance)');
            } catch (\Exception $e) {
                // Constraints might already exist or fail for other reasons
                // Continue without failing the migration
            }
        }
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
