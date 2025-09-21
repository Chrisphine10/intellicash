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
        Schema::create('charge_limits', function (Blueprint $table) {
            $table->id();
            $table->decimal('minimum_amount', 18, 2);
            $table->decimal('maximum_amount', 18, 2);
            $table->decimal('fixed_charge', 10, 2);
            $table->decimal('charge_in_percentage', 10, 2);
            $table->bigInteger('gateway_id');
            $table->string('gateway_type');
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('charge_limits');
    }
};
