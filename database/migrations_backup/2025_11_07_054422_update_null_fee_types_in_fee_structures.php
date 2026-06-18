<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Update all NULL or empty fee_type values to 'General Fee'
     */
    public function up(): void
    {
        // Update all records where fee_type is NULL or empty string
        DB::table('fee_structures')
            ->whereNull('fee_type')
            ->orWhere('fee_type', '')
            ->update(['fee_type' => 'General Fee']);
        
        // Log the number of updated records
        $updatedCount = DB::table('fee_structures')
            ->where('fee_type', 'General Fee')
            ->count();
            
        \Log::info("Updated {$updatedCount} fee structures with NULL/empty fee_type to 'General Fee'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally, you could revert 'General Fee' back to NULL
        // But this is not recommended as it would lose data
        // DB::table('fee_structures')
        //     ->where('fee_type', 'General Fee')
        //     ->update(['fee_type' => null]);
    }
};
