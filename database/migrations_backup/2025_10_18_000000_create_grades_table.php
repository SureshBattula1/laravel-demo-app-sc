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
        if (!Schema::hasTable('grades')) {
            Schema::create('grades', function (Blueprint $table) {
                $table->id();
                $table->string('value', 20)->unique()->comment('Grade number (1-12)');
                $table->string('label', 100)->comment('Display name (Grade 1, Grade 2, etc.)');
                $table->text('description')->nullable()->comment('Additional information about the grade');
                $table->boolean('is_active')->default(true)->comment('Whether the grade is active');
                $table->timestamps();
                
                $table->index('value');
                $table->index('is_active');
            });

            // Seed default grades
            $grades = [
                ['value' => '1', 'label' => 'Grade 1', 'is_active' => true],
                ['value' => '2', 'label' => 'Grade 2', 'is_active' => true],
                ['value' => '3', 'label' => 'Grade 3', 'is_active' => true],
                ['value' => '4', 'label' => 'Grade 4', 'is_active' => true],
                ['value' => '5', 'label' => 'Grade 5', 'is_active' => true],
                ['value' => '6', 'label' => 'Grade 6', 'is_active' => true],
                ['value' => '7', 'label' => 'Grade 7', 'is_active' => true],
                ['value' => '8', 'label' => 'Grade 8', 'is_active' => true],
                ['value' => '9', 'label' => 'Grade 9', 'is_active' => true],
                ['value' => '10', 'label' => 'Grade 10', 'is_active' => true],
                ['value' => '11', 'label' => 'Grade 11', 'is_active' => true],
                ['value' => '12', 'label' => 'Grade 12', 'is_active' => true],
            ];

            foreach ($grades as $grade) {
                DB::table('grades')->insert([
                    'value' => $grade['value'],
                    'label' => $grade['label'],
                    'is_active' => $grade['is_active'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};

