<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE class_upgrades MODIFY COLUMN promotion_status ENUM('Promoted', 'Detained', 'Left', 'Graduated', 'Reverted') DEFAULT 'Promoted'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE class_upgrades MODIFY COLUMN promotion_status ENUM('Promoted', 'Detained', 'Left', 'Graduated') DEFAULT 'Promoted'");
    }
};
