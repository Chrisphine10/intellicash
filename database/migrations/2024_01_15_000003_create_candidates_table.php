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
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('bio')->nullable();
            $table->text('manifesto')->nullable();
            $table->string('photo')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('election_id');
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();
        });

        // Add foreign key constraints only if referenced tables exist
        if (Schema::hasTable('elections')) {
            Schema::table('candidates', function (Blueprint $table) {
                $table->foreign('election_id')->references('id')->on('elections')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('members')) {
            Schema::table('candidates', function (Blueprint $table) {
                $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('tenants')) {
            Schema::table('candidates', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candidates');
    }
};
