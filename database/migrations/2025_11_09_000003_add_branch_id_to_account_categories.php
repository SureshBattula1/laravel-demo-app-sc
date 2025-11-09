<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('account_categories', function (Blueprint $table) {
            // Add branch_id column
            $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->onDelete('cascade');
            
            // Add index for branch filtering
            $table->index(['branch_id', 'type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_categories', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropIndex(['branch_id', 'type', 'is_active']);
            $table->dropColumn('branch_id');
        });
    }
};
