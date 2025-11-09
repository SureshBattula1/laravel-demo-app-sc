<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Add performance indexes for accounts module
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Composite indexes for frequently used query combinations
            
            // For dashboard queries: status + financial_year + type
            if (!$this->indexExists('transactions', 'transactions_status_financial_year_type_index')) {
                $table->index(['status', 'financial_year', 'type'], 'transactions_status_financial_year_type_index');
            }
            
            // For category filtering: category_id + financial_year + status
            if (!$this->indexExists('transactions', 'transactions_category_financial_year_status_index')) {
                $table->index(['category_id', 'financial_year', 'status'], 'transactions_category_financial_year_status_index');
            }
            
            // For branch filtering: branch_id + financial_year + status
            if (!$this->indexExists('transactions', 'transactions_branch_financial_year_status_index')) {
                $table->index(['branch_id', 'financial_year', 'status'], 'transactions_branch_financial_year_status_index');
            }
            
            // For recent transactions: financial_year + transaction_date
            if (!$this->indexExists('transactions', 'transactions_financial_year_date_index')) {
                $table->index(['financial_year', 'transaction_date'], 'transactions_financial_year_date_index');
            }
            
            // For monthly trends: transaction_date + status + type
            if (!$this->indexExists('transactions', 'transactions_date_status_type_index')) {
                $table->index(['transaction_date', 'status', 'type'], 'transactions_date_status_type_index');
            }
        });

        // Add indexes to account_categories if not exist
        Schema::table('account_categories', function (Blueprint $table) {
            // Composite index for type + is_active filtering
            if (!$this->indexExists('account_categories', 'account_categories_type_active_name_index')) {
                $table->index(['type', 'is_active', 'name'], 'account_categories_type_active_name_index');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_status_financial_year_type_index');
            $table->dropIndex('transactions_category_financial_year_status_index');
            $table->dropIndex('transactions_branch_financial_year_status_index');
            $table->dropIndex('transactions_financial_year_date_index');
            $table->dropIndex('transactions_date_status_type_index');
        });

        Schema::table('account_categories', function (Blueprint $table) {
            $table->dropIndex('account_categories_type_active_name_index');
        });
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        
        $result = DB::select(
            "SELECT COUNT(*) as count FROM information_schema.statistics 
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$database, $table, $index]
        );
        
        return $result[0]->count > 0;
    }
};

