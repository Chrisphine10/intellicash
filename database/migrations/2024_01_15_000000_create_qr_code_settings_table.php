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
        Schema::create('qr_code_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->boolean('enabled')->default(false);
            $table->boolean('ethereum_enabled')->default(false);
            $table->string('ethereum_network')->default('mainnet'); // mainnet, goerli, sepolia, polygon
            $table->string('ethereum_rpc_url')->nullable();
            $table->string('ethereum_contract_address')->nullable();
            $table->string('ethereum_account_address')->nullable();
            $table->text('ethereum_private_key')->nullable(); // encrypted
            $table->integer('qr_code_size')->default(200);
            $table->string('qr_code_error_correction')->default('H'); // L, M, Q, H
            $table->integer('verification_cache_days')->default(30);
            $table->boolean('auto_generate_qr')->default(true);
            $table->boolean('include_blockchain_verification')->default(false);
            $table->json('custom_settings')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qr_code_settings');
    }
};
