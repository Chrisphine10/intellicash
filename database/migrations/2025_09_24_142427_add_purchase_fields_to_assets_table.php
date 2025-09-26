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
            $table->string('supplier_name')->nullable()->after('notes');
            $table->string('invoice_number')->nullable()->after('supplier_name');
            $table->enum('payment_method', ['bank_transfer', 'cash', 'credit'])->nullable()->after('invoice_number');
            $table->unsignedBigInteger('bank_account_id')->nullable()->after('payment_method');
            
            $table->foreign('bank_account_id')->references('id')->on('bank_accounts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn(['supplier_name', 'invoice_number', 'payment_method', 'bank_account_id']);
        });
    }
};
