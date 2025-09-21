<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('loan_repayments', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('loan_id');
            $table->date('repayment_date');
            $table->decimal('amount_to_pay', 10, 2);
            $table->decimal('penalty', 10, 2);
            $table->decimal('principal_amount', 10, 2);
            $table->decimal('interest', 10, 2);
            $table->decimal('balance', 10, 2);
            $table->tinyInteger('status')->default(0);
            $table->timestamp('upcomming_notification')->nullable();
            $table->timestamp('overdue_notification')->nullable();
            $table->timestamp('final_notification')->nullable();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('loan_repayments');
    }
};
