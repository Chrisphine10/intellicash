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
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // e.g., "tenant1" in example.com/tenant1
            $table->string('name');
            $table->string('membership_type', 50)->nullable()->comment('trial | member');
            $table->bigInteger('package_id')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->date('subscription_date')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamp('t_email_send_at')->nullable();
            $table->timestamp('s_email_send_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
