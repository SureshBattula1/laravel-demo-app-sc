<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_types', function (Blueprint $table) {
            if (!Schema::hasColumn('fee_types', 'branch_id')) {
                $table->foreignId('branch_id')->after('id')->constrained('branches')->onDelete('cascade');
            }
            if (!Schema::hasColumn('fee_types', 'description')) {
                $table->text('description')->nullable()->after('code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fee_types', function (Blueprint $table) {
            if (Schema::hasColumn('fee_types', 'branch_id')) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            }
            if (Schema::hasColumn('fee_types', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};

