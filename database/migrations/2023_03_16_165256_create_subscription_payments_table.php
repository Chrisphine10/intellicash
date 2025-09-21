<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('order_id');
            $table->string('payment_method', 30);
            $table->bigInteger('package_id');
            $table->decimal('amount', 10, 2);
            $table->tinyInteger('status')->default(0);
            $table->text('extra')->nullable();
            $table->bigInteger('created_user_id')->unsigned();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        Schema::dropIfExists('subscription_payments');
    }
};
