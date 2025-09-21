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
        Schema::create('vsla_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->decimal('share_amount', 15, 2)->default(0);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->decimal('welfare_amount', 15, 2)->default(0);
            $table->decimal('loan_interest_rate', 5, 2)->default(0);
            $table->enum('meeting_frequency', ['weekly', 'monthly', 'custom'])->default('weekly');
            $table->integer('custom_meeting_days')->nullable();
            $table->time('meeting_time')->default('10:00:00');
            $table->string('chairperson_role', 100)->default('Chairperson');
            $table->string('treasurer_role', 100)->default('Treasurer');
            $table->string('secretary_role', 100)->default('Secretary');
            $table->boolean('auto_approve_loans')->default(false);
            $table->decimal('max_loan_amount', 15, 2)->nullable();
            $table->integer('max_loan_duration_days')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vsla_settings');
    }
};
