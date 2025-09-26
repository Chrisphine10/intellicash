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
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedBigInteger('member_id')->nullable()->after('user_id');
            $table->foreign('member_id')->references('id')->on('members')->onDelete('set null');
            $table->index(['tenant_id', 'member_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['member_id']);
            $table->dropIndex(['tenant_id', 'member_id']);
            $table->dropColumn('member_id');
        });
    }
};
