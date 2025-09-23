<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('votes', function (Blueprint $table) {
            // Blockchain security fields
            $table->string('blockchain_hash', 64)->nullable()->after('tenant_id');
            $table->text('encrypted_data')->nullable()->after('blockchain_hash');
            $table->boolean('is_verified')->default(false)->after('encrypted_data');
            $table->timestamp('verification_timestamp')->nullable()->after('is_verified');
            
            // Security tracking fields
            $table->string('ip_address', 45)->nullable()->after('verification_timestamp');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('device_fingerprint', 64)->nullable()->after('user_agent');
            $table->decimal('latitude', 10, 8)->nullable()->after('device_fingerprint');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            
            // Digital signature
            $table->string('digital_signature', 64)->nullable()->after('longitude');
            
            // Security score
            $table->integer('security_score')->default(0)->after('digital_signature');
            
            // Add indexes for performance
            $table->index('blockchain_hash');
            $table->index('is_verified');
            $table->index('ip_address');
            $table->index('security_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->dropIndex(['blockchain_hash']);
            $table->dropIndex(['is_verified']);
            $table->dropIndex(['ip_address']);
            $table->dropIndex(['security_score']);
            
            $table->dropColumn([
                'blockchain_hash',
                'encrypted_data',
                'is_verified',
                'verification_timestamp',
                'ip_address',
                'user_agent',
                'device_fingerprint',
                'latitude',
                'longitude',
                'digital_signature',
                'security_score',
            ]);
        });
    }
};
