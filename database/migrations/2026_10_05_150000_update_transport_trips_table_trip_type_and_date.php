<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transport_trips') && DB::getDriverName() === 'mysql') {
            // Modify trip_type to string and trip_date to nullable
            DB::statement("ALTER TABLE transport_trips MODIFY COLUMN trip_type VARCHAR(50) DEFAULT 'Pickup'");
            DB::statement('ALTER TABLE transport_trips MODIFY COLUMN trip_date DATE NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transport_trips') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transport_trips MODIFY COLUMN trip_type ENUM('Morning', 'Afternoon', 'Special') DEFAULT 'Morning'");
            DB::statement('ALTER TABLE transport_trips MODIFY COLUMN trip_date DATE NOT NULL');
        }
    }
};
