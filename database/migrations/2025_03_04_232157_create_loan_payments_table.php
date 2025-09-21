<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->date('paid_at');
            $table->decimal('late_penalties', 10, 2);
            $table->decimal('interest', 10, 2);
            $table->decimal('repayment_amount', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('member_id');
            $table->bigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('repayment_id');
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->foreign('repayment_id')->references('id')->on('loan_repayments')->cascadeOnDelete();
            $table->foreign('loan_id')->references('id')->on('loans')->cascadeOnDelete();
            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('loan_payments');
    }
};
