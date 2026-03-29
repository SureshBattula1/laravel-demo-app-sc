<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Normalize legacy rows so SMS vs WhatsApp filtering is reliable.
        DB::table('sms_bulk_queue')
            ->whereNull('channel')
            ->update(['channel' => 'sms']);

        DB::table('sms_bulk_queue')
            ->where('channel', '')
            ->update(['channel' => 'sms']);
    }

    public function down(): void
    {
        // No safe rollback (values are normalized).
    }
};
