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
        Schema::create('withdraw_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('method_id');
            $table->unsignedBigInteger('debit_account_id');
            $table->decimal('amount', 10, 2);
            $table->decimal('converted_amount', 10, 2);
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->string('attachment')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->bigInteger('transaction_id')->nullable();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('method_id')->references('id')->on('withdraw_methods')->cascadeOnDelete();
            $table->foreign('debit_account_id')->references('id')->on('savings_accounts')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdraw_requests');
    }
};
