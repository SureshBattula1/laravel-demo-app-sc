<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Change qualification from JSON to TEXT column
        DB::statement('ALTER TABLE teachers MODIFY COLUMN qualification TEXT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to JSON
        DB::statement('ALTER TABLE teachers MODIFY COLUMN qualification JSON NULL');
    }
};
