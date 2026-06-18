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
        if (Schema::hasTable('grades')) {
            Schema::table('grades', function (Blueprint $table) {
                if (!Schema::hasColumn('grades', 'order')) {
                    $table->integer('order')->default(0)->after('value')->comment('Display order');
                    $table->index('order', 'idx_grades_order');
                }
                
                if (!Schema::hasColumn('grades', 'category')) {
                    $table->string('category', 50)->nullable()->after('order')->comment('Pre-Primary, Primary, Middle, Secondary, Senior-Secondary');
                    $table->index('category', 'idx_grades_category');
                }
            });
            
            // Update existing grades with order
            DB::statement("
                UPDATE grades 
                SET `order` = CASE 
                    WHEN value = '1' THEN 5
                    WHEN value = '2' THEN 6
                    WHEN value = '3' THEN 7
                    WHEN value = '4' THEN 8
                    WHEN value = '5' THEN 9
                    WHEN value = '6' THEN 10
                    WHEN value = '7' THEN 11
                    WHEN value = '8' THEN 12
                    WHEN value = '9' THEN 13
                    WHEN value = '10' THEN 14
                    WHEN value = '11' THEN 15
                    WHEN value = '12' THEN 16
                    ELSE 99
                END,
                category = CASE 
                    WHEN value IN ('1', '2', '3', '4', '5') THEN 'Primary'
                    WHEN value IN ('6', '7', '8') THEN 'Middle'
                    WHEN value IN ('9', '10') THEN 'Secondary'
                    WHEN value IN ('11', '12') THEN 'Senior-Secondary'
                    ELSE NULL
                END
                WHERE value IN ('1','2','3','4','5','6','7','8','9','10','11','12')
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            if (Schema::hasColumn('grades', 'order')) {
                $table->dropIndex('idx_grades_order');
                $table->dropColumn('order');
            }
            if (Schema::hasColumn('grades', 'category')) {
                $table->dropIndex('idx_grades_category');
                $table->dropColumn('category');
            }
        });
    }
};

