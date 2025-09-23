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
            $table->foreignId('election_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // created, started, voted, closed, result_calculated, etc.
            $table->text('details')->nullable(); // JSON details about the action
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voting_audit_logs');
    }
};
