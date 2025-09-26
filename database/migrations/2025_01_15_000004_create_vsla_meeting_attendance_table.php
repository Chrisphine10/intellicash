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
        Schema::create('vsla_meeting_attendance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id');
            $table->unsignedBigInteger('member_id');
            $table->boolean('present')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['meeting_id', 'member_id']);
        });

        // Add foreign key constraints only if referenced tables exist
        if (Schema::hasTable('vsla_meetings')) {
            Schema::table('vsla_meeting_attendance', function (Blueprint $table) {
                $table->foreign('meeting_id')->references('id')->on('vsla_meetings')->onDelete('cascade');
            });
        }
        
        if (Schema::hasTable('members')) {
            Schema::table('vsla_meeting_attendance', function (Blueprint $table) {
                $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vsla_meeting_attendance');
    }
};
