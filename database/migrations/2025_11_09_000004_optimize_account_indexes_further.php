<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Further optimize for specific queries
     */
    public function up(): void
    {
        // Add covering index for transactions list queries
        DB::statement('CREATE INDEX IF NOT EXISTS transactions_type_deleted_date_covering 
                      ON transactions(type, deleted_at, transaction_date, status, category_id, branch_id)');
        
        // Add covering index for categories
        DB::statement('CREATE INDEX IF NOT EXISTS categories_active_type_covering 
                      ON account_categories(is_active, type, name)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS transactions_type_deleted_date_covering ON transactions');
        DB::statement('DROP INDEX IF EXISTS categories_active_type_covering ON account_categories');
    }
};

