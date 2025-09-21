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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->datetime('expense_date');
            $table->unsignedBigInteger('expense_category_id');
            $table->decimal('amount', 10, 2);
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('created_user_id')->nullable();
            $table->bigInteger('updated_user_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();

            $table->foreign('expense_category_id')->references('id')->on('expense_categories')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
