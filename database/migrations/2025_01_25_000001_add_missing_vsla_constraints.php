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
        if (Schema::hasTable('vsla_transactions')) {
            Schema::table('vsla_transactions', function (Blueprint $table) {
                // Check if bank_account_id column doesn't exist before adding it
                if (!Schema::hasColumn('vsla_transactions', 'bank_account_id')) {
                    if (Schema::hasTable('bank_accounts')) {
                        $table->foreignId('bank_account_id')->nullable()->constrained('bank_accounts')->onDelete('set null')->after('savings_account_id');
                    } else {
                        $table->unsignedBigInteger('bank_account_id')->nullable()->after('savings_account_id');
                    }
                }
            
                // Check if shares column doesn't exist before adding it
                if (!Schema::hasColumn('vsla_transactions', 'shares')) {
                    $table->integer('shares')->nullable()->default(0)->after('amount');
                }
                
                // Add indexes for better performance (only if they don't exist)
                try {
                    $table->index(['tenant_id', 'member_id']);
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
                
                try {
                    $table->index(['tenant_id', 'transaction_type']);
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
                
                try {
                    $table->index(['tenant_id', 'status']);
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
                
                try {
                    $table->index(['meeting_id', 'member_id']);
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
            });
        }

        if (Schema::hasTable('vsla_meetings')) {
            Schema::table('vsla_meetings', function (Blueprint $table) {
                // Add indexes for better performance (only if they don't exist)
                try {
                    $table->index(['tenant_id', 'meeting_date']);
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
                
                try {
                    $table->index(['tenant_id', 'status']);
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
            });
        }

        if (Schema::hasTable('vsla_cycles')) {
            Schema::table('vsla_cycles', function (Blueprint $table) {
                // Add indexes for better performance (only if they don't exist)
                try {
                    $table->index(['status', 'created_at']);
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vsla_transactions', function (Blueprint $table) {
            // Only drop columns that were added by this migration
            if (Schema::hasColumn('vsla_transactions', 'bank_account_id')) {
                $table->dropForeign(['bank_account_id']);
                $table->dropColumn('bank_account_id');
            }
            
            if (Schema::hasColumn('vsla_transactions', 'shares')) {
                $table->dropColumn('shares');
            }
            
            // Drop indexes that were added by this migration
            try {
                $table->dropIndex(['tenant_id', 'member_id']);
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
            
            try {
                $table->dropIndex(['tenant_id', 'transaction_type']);
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
            
            try {
                $table->dropIndex(['tenant_id', 'status']);
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
            
            try {
                $table->dropIndex(['meeting_id', 'member_id']);
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
        });

        Schema::table('vsla_meetings', function (Blueprint $table) {
            // Drop indexes that were added by this migration
            try {
                $table->dropIndex(['tenant_id', 'meeting_date']);
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
            
            try {
                $table->dropIndex(['tenant_id', 'status']);
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
        });

        Schema::table('vsla_cycles', function (Blueprint $table) {
            // Drop indexes that were added by this migration
            try {
                $table->dropIndex(['status', 'created_at']);
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
        });
    }
};
