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
        Schema::table('loan_products', function (Blueprint $table) {
            $table->decimal('loan_insurance_fee', 10, 2)->default(0)->after('loan_processing_fee_type');
            $table->tinyInteger('loan_insurance_fee_type')->default(0)->comment('0 = Fixed | 1 = Percentage')->after('loan_insurance_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_products', function (Blueprint $table) {
            $table->dropColumn(['loan_insurance_fee', 'loan_insurance_fee_type']);
        });
    }
};
