<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill: old rows used DB default "queued"; new sends use "processing" / "completed".
        DB::table('sms_bulk_queue')->where('status', 'queued')->update(['status' => 'processing']);

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE sms_bulk_queue MODIFY status VARCHAR(32) NOT NULL DEFAULT 'processing'");
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE sms_bulk_queue MODIFY status VARCHAR(32) NOT NULL DEFAULT 'queued'");
        }
    }
};
