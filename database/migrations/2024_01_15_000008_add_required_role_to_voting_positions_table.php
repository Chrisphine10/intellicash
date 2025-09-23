<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('voting_positions', function (Blueprint $table) {
            $table->string('required_role', 50)->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('voting_positions', function (Blueprint $table) {
            $table->dropColumn('required_role');
        });
    }
};
