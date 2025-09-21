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
        Schema::create('automatic_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30);
            $table->string('slug', 30);
            $table->string('image')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->tinyInteger('is_crypto')->default(0);
            $table->text('parameters')->nullable();
            $table->string('currency', 3)->nullable();
            $table->text('supported_currencies')->nullable();
            $table->text('extra')->nullable();
            $table->decimal('exchange_rate', 10, 6)->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('automatic_gateways');
    }
};
