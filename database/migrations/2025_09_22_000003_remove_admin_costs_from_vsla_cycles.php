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
        Schema::table('vsla_cycles', function (Blueprint $table) {
            if (Schema::hasColumn('vsla_cycles', 'admin_costs')) {
                $table->dropColumn('admin_costs');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vsla_cycles', function (Blueprint $table) {
            $table->decimal('admin_costs', 15, 2)->default(0)->after('total_available_for_shareout');
        });
    }
};
