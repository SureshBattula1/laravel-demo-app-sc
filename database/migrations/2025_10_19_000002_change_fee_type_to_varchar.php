<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change fee_type from ENUM to VARCHAR to support dynamic fee types
        DB::statement('ALTER TABLE fee_structures MODIFY fee_type VARCHAR(255) NOT NULL');
    }

    public function down(): void
    {
        // Revert to ENUM (only if needed)
        DB::statement("ALTER TABLE fee_structures MODIFY fee_type ENUM('Tuition', 'Library', 'Laboratory', 'Sports', 'Transport', 'Exam', 'Other') NOT NULL");
    }
};

