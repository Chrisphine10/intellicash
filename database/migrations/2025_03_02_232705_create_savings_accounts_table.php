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
        Schema::create('savings_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number', 30);
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('savings_product_id');
            $table->integer('status')->comment('1 = action | 2 = Deactivate');
            $table->decimal('opening_balance', 10, 2);
            $table->text('description')->nullable();
            $table->integer('last_deducted_year')->nullable();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('created_user_id')->nullable();
            $table->bigInteger('updated_user_id')->nullable();
            $table->timestamps();

            $table->foreign('member_id')->references('id')->on('members')->cascadeOnDelete();
            $table->foreign('savings_product_id')->references('id')->on('savings_products')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('savings_accounts');
    }
};
