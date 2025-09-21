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
            $table->foreignId('meeting_id')->constrained('vsla_meetings')->onDelete('cascade');
            $table->foreignId('member_id')->constrained('members')->onDelete('cascade');
            $table->boolean('present')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['meeting_id', 'member_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vsla_meeting_attendance');
    }
};
