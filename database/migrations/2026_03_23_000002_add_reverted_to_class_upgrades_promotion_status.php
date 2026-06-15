<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            if (Schema::hasColumn('class_upgrades', 'promotion_status')) {
                Schema::table('class_upgrades', function (Blueprint $table) {
                    $table->string('promotion_status')->default('Promoted')->change();
                });
            }
            return;
        }

        DB::statement("ALTER TABLE class_upgrades MODIFY COLUMN promotion_status ENUM('Promoted', 'Detained', 'Left', 'Graduated', 'Reverted') DEFAULT 'Promoted'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE class_upgrades MODIFY COLUMN promotion_status ENUM('Promoted', 'Detained', 'Left', 'Graduated') DEFAULT 'Promoted'");
    }
};
