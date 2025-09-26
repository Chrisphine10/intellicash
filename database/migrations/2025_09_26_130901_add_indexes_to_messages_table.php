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
        Schema::table('messages', function (Blueprint $table) {
            // Add indexes for better query performance
            $table->index(['recipient_id', 'status'], 'idx_messages_recipient_status');
            $table->index(['sender_id', 'parent_id'], 'idx_messages_sender_parent');
            $table->index(['tenant_id', 'created_at'], 'idx_messages_tenant_created');
            $table->index('uuid', 'idx_messages_uuid');
        });

        Schema::table('message_attachments', function (Blueprint $table) {
            $table->index('message_id', 'idx_message_attachments_message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_recipient_status');
            $table->dropIndex('idx_messages_sender_parent');
            $table->dropIndex('idx_messages_tenant_created');
            $table->dropIndex('idx_messages_uuid');
        });

        Schema::table('message_attachments', function (Blueprint $table) {
            $table->dropIndex('idx_message_attachments_message_id');
        });
    }
};
