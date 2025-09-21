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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('user_type', 20)->comment('superadmin | admin | user | customer');
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->tinyInteger('tenant_owner')->nullable();
            $table->bigInteger('role_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->string('profile_picture')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->tinyInteger('all_branch_access')->default(0);
            $table->string('country_code', 10)->nullable();
            $table->string('mobile', 50)->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('provider')->nullable();    // Social Login
            $table->string('provider_id')->nullable(); // Social Login
            $table->text('custom_fields')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->string('email_tenant')->virtualAs(
                "CONCAT(email, '-', COALESCE(tenant_id, 0))"
            );

            $table->unique('email_tenant');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
