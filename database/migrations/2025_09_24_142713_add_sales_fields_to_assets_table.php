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
        Schema::table('assets', function (Blueprint $table) {
            $table->decimal('sale_price', 10, 2)->nullable()->after('bank_account_id');
            $table->date('sale_date')->nullable()->after('sale_price');
            $table->string('buyer_name')->nullable()->after('sale_date');
            $table->text('sale_reason')->nullable()->after('buyer_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['sale_price', 'sale_date', 'buyer_name', 'sale_reason']);
        });
    }
};
