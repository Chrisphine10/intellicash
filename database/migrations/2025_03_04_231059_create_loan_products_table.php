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
        Schema::create('loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('loan_id_prefix', 10)->nullable();
            $table->bigInteger('starting_loan_id')->nullable();
            $table->decimal('minimum_amount', 10, 2);
            $table->decimal('maximum_amount', 10, 2);
            $table->decimal('late_payment_penalties', 10, 2);
            $table->text('description')->nullable();
            $table->decimal('interest_rate', 10, 2);
            $table->string('interest_type');
            $table->integer('term');
            $table->string('term_period', 15);
            $table->tinyInteger('status')->default(1);
            $table->decimal('loan_application_fee', 10, 2)->default(0);
            $table->tinyInteger('loan_application_fee_type')->default(0)->comment('0 = Fixed | 1 = Percentage');
            $table->decimal('loan_processing_fee', 10, 2)->default(0);
            $table->tinyInteger('loan_processing_fee_type')->default(0)->comment('0 = Fixed | 1 = Percentage');
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_products');
    }
};
