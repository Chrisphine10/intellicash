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
        Schema::create('voting_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('election_id');
            $table->unsignedBigInteger('member_id')->nullable();
            $table->string('action', 50); // created, started, voted, closed, result_calculated, etc.
            $table->text('details')->nullable(); // JSON details about the action
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('performed_by');
            $table->timestamps();
        });

        // Add foreign key constraints only if referenced tables exist
        if (Schema::hasTable('elections')) {
            Schema::table('voting_audit_logs', function (Blueprint $table) {
                $table->foreign('election_id')->references('id')->on('elections')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('members')) {
            Schema::table('voting_audit_logs', function (Blueprint $table) {
                $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();
            });
        }
        
        if (Schema::hasTable('tenants')) {
            Schema::table('voting_audit_logs', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('users')) {
            Schema::table('voting_audit_logs', function (Blueprint $table) {
                $table->foreign('performed_by')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voting_audit_logs');
    }
};
