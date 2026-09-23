<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill users.company_id from their branch's school so tenant filters work.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE users u
            INNER JOIN branches b ON u.branch_id = b.id
            INNER JOIN schools s ON b.school_id = s.id
            SET u.company_id = s.company_id
            WHERE u.company_id IS NULL
              AND u.deleted_at IS NULL
              AND s.company_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        // Irreversible data repair
    }
};
