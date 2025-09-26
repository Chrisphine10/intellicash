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
        Schema::table('esignature_documents', function (Blueprint $table) {
            $table->string('document_hash')->nullable()->after('file_type');
            $table->index('document_hash');
        });

        Schema::table('esignature_signatures', function (Blueprint $table) {
            $table->string('signature_hash')->nullable()->after('signature_data');
            $table->index('signature_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('esignature_documents', function (Blueprint $table) {
            $table->dropIndex(['document_hash']);
            $table->dropColumn('document_hash');
        });

        Schema::table('esignature_signatures', function (Blueprint $table) {
            $table->dropIndex(['signature_hash']);
            $table->dropColumn('signature_hash');
        });
    }
};
