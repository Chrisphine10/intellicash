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
        Schema::create('legal_templates', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 3); // ISO country code (KEN, UGA, TZA, etc.)
            $table->string('country_name');
            $table->string('template_name');
            $table->string('template_type'); // 'general', 'microfinance', 'sme', 'agricultural', 'personal'
            $table->string('version')->default('1.0');
            $table->longText('terms_and_conditions');
            $table->longText('privacy_policy');
            $table->text('description')->nullable();
            $table->json('applicable_laws')->nullable(); // Array of applicable laws
            $table->json('regulatory_bodies')->nullable(); // Array of regulatory bodies
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system_template')->default(true); // System vs custom templates
            $table->string('language_code', 5)->default('en'); // en, sw, fr, etc.
            $table->timestamps();
            
            $table->index(['country_code', 'template_type', 'is_active']);
            $table->index(['is_system_template', 'is_active']);
            $table->unique(['country_code', 'template_name', 'template_type', 'version'], 'legal_templates_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_templates');
    }
};
