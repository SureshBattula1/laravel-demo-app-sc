<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Section;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;

class SectionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('📚 Creating Sections for All Grades...');
        $this->command->info(str_repeat('=', 60));
        
        // Get the main branch
        $branch = Branch::first();
        if (!$branch) {
            $this->command->error('❌ No branches found!');
            return;
        }

        // All grades
        $grades = DB::table('grades')->orderBy('order')->pluck('value')->toArray();
        
        // Sections A, B, C, D
        $sectionNames = ['A', 'B', 'C', 'D'];
        
        $created = 0;
        $skipped = 0;

        foreach ($grades as $grade) {
            $this->command->info("📖 Creating sections for Grade: {$grade}");
            
            foreach ($sectionNames as $sectionName) {
                // Check if section already exists
                $exists = Section::where('branch_id', $branch->id)
                    ->where('grade_level', $grade)
                    ->where('name', $sectionName)
                    ->exists();
                
                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Create section
                Section::create([
                    'branch_id' => $branch->id,
                    'name' => $sectionName,
                    'code' => strtoupper($branch->code ?? 'MAIN') . '-' . $grade . '-' . $sectionName,
                    'grade_level' => $grade,
                    'capacity' => $this->getCapacityForGrade($grade),
                    'current_strength' => 0,
                    'room_number' => 'Room-' . $grade . $sectionName,
                    'class_teacher_id' => null,
                    'is_active' => true,
                    'description' => "Section {$sectionName} for Grade {$grade}",
                ]);

                $created++;
                $this->command->info("  ✓ Section {$sectionName} created");
            }
        }

        $this->command->info('');
        $this->command->info(str_repeat('=', 60));
        $this->command->info("✅ Sections Created: {$created}");
        $this->command->info("⏭️  Sections Skipped: {$skipped}");
        $this->command->info(str_repeat('=', 60));
    }

    private function getCapacityForGrade(string $grade): int
    {
        // Smaller capacity for pre-primary
        if (in_array($grade, ['PlaySchool', 'Nursery'])) {
            return 20;
        }
        
        if (in_array($grade, ['LKG', 'UKG'])) {
            return 25;
        }
        
        // Primary grades
        if (in_array($grade, ['1', '2', '3', '4', '5'])) {
            return 30;
        }
        
        // Middle and above
        return 35;
    }
}

