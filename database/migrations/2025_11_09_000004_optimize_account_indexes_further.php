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
        $this->createIndex(
            'transactions',
            'transactions_type_deleted_date_covering',
            'type, deleted_at, transaction_date, status, category_id, branch_id'
        );

        // Add covering index for categories
        $this->createIndex(
            'account_categories',
            'categories_active_type_covering',
            'is_active, type, name'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropIndex('transactions', 'transactions_type_deleted_date_covering');
        $this->dropIndex('account_categories', 'categories_active_type_covering');
    }

    /**
     * Create an index if it doesn't already exist (driver-portable).
     * MySQL 8 has no CREATE INDEX IF NOT EXISTS, so guard via information_schema.
     */
    private function createIndex(string $table, string $index, string $columns): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement("CREATE INDEX IF NOT EXISTS {$index} ON {$table}({$columns})");
            return;
        }

        if (! $this->indexExists($table, $index)) {
            DB::statement("CREATE INDEX {$index} ON {$table}({$columns})");
        }
    }

    private function dropIndex(string $table, string $index): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            DB::statement("DROP INDEX IF EXISTS {$index}");
            return;
        }

        if ($this->indexExists($table, $index)) {
            DB::statement("DROP INDEX {$index} ON {$table}");
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $result = $connection->select(
            "SELECT COUNT(*) as count FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
            [$table, $index]
        );

        return $result[0]->count > 0;
    }
};

