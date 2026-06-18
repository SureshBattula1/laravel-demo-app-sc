<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrePrimaryGradesSeeder extends Seeder
{
    /**
     * Run the database seeds - Add pre-primary grades
     */
    public function run(): void
    {
        $this->command->info('🎓 Adding Pre-Primary Grades...');
        
        $prePrimaryGrades = [
            [
                'value' => 'PlaySchool',
                'label' => 'Play School',
                'description' => 'Pre-nursery level for toddlers (Age 2-3)',
                'category' => 'Pre-Primary',
                'order' => 1,
                'is_active' => true
            ],
            [
                'value' => 'Nursery',
                'label' => 'Nursery',
                'description' => 'Nursery level for early learners (Age 3-4)',
                'category' => 'Pre-Primary',
                'order' => 2,
                'is_active' => true
            ],
            [
                'value' => 'LKG',
                'label' => 'Lower Kindergarten (LKG)',
                'description' => 'Lower Kindergarten for young children (Age 4-5)',
                'category' => 'Pre-Primary',
                'order' => 3,
                'is_active' => true
            ],
            [
                'value' => 'UKG',
                'label' => 'Upper Kindergarten (UKG)',
                'description' => 'Upper Kindergarten preparing for Grade 1 (Age 5-6)',
                'category' => 'Pre-Primary',
                'order' => 4,
                'is_active' => true
            ],
        ];
        
        foreach ($prePrimaryGrades as $grade) {
            DB::table('grades')->updateOrInsert(
                ['value' => $grade['value']],
                [
                    'label' => $grade['label'],
                    'description' => $grade['description'],
                    'category' => $grade['category'],
                    'order' => $grade['order'],
                    'is_active' => $grade['is_active'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
            
            $this->command->info("  ✓ {$grade['label']} added");
        }
        
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('✅ Pre-Primary Grades Added Successfully!');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('📚 Total Grades: ' . DB::table('grades')->count());
        $this->command->info('');
        $this->command->info('Grade System:');
        $this->command->info('  🧸 Pre-Primary: Play School, Nursery, LKG, UKG');
        $this->command->info('  📖 Primary: Grades 1-5');
        $this->command->info('  🏫 Middle: Grades 6-8');
        $this->command->info('  📚 Secondary: Grades 9-10');
        $this->command->info('  🎓 Senior Secondary: Grades 11-12');
        $this->command->info('═══════════════════════════════════════════════════════');
    }
}

