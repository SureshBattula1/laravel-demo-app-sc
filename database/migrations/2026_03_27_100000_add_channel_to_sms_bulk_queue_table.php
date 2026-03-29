<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_bulk_queue', function (Blueprint $table) {
            $table->string('channel', 16)->default('sms')->after('branch_id');
            $table->index(['branch_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::table('sms_bulk_queue', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'channel']);
            $table->dropColumn('channel');
        });
    }
};
