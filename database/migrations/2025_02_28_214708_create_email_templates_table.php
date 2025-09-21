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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50);
            $table->string('subject');
            $table->text('email_body')->nullable();
            $table->text('sms_body')->nullable();
            $table->text('notification_body')->nullable();
            $table->text('shortcode')->nullable();
            $table->tinyInteger('email_status')->default(0);
            $table->tinyInteger('sms_status')->default(0);
            $table->tinyInteger('notification_status')->default(0);
            $table->tinyInteger('template_mode')->default(0)->comment('0 = all, 1 = email, 2 = sms, 3 = notification');
            $table->string('template_type', 20)->default('admin')->comment('admin, tenant');
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
        Schema::dropIfExists('email_templates');
    }
};
