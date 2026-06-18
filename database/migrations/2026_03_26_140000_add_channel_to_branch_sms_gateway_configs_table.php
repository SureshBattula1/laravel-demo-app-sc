<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate SMS vs WhatsApp gateway rows per branch (same provider may exist twice with different channels).
     */
    public function up(): void
    {
        Schema::table('branch_sms_gateway_configs', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'provider']);
        });

        Schema::table('branch_sms_gateway_configs', function (Blueprint $table) {
            $table->string('channel', 16)->default('sms')->after('provider');
        });

        Schema::table('branch_sms_gateway_configs', function (Blueprint $table) {
            $table->unique(
                ['branch_id', 'provider', 'channel'],
                'branch_sms_gateway_branch_provider_channel_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('branch_sms_gateway_configs', function (Blueprint $table) {
            $table->dropUnique('branch_sms_gateway_branch_provider_channel_unique');
        });

        Schema::table('branch_sms_gateway_configs', function (Blueprint $table) {
            $table->dropColumn('channel');
        });

        Schema::table('branch_sms_gateway_configs', function (Blueprint $table) {
            $table->unique(['branch_id', 'provider']);
        });
    }
};
