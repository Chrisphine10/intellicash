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
        Schema::create('asset_maintenances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('asset_id');
            $table->string('maintenance_type'); // scheduled, emergency, repair, inspection
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('scheduled_date');
            $table->date('completed_date')->nullable();
            $table->decimal('cost', 10, 2)->default(0);
            $table->string('status')->default('scheduled'); // scheduled, in_progress, completed, cancelled
            $table->text('notes')->nullable();
            $table->string('performed_by')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
            $table->index(['asset_id', 'status']);
            $table->index(['scheduled_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_maintenances');
    }
};
