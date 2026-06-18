<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestGrades extends Command
{
    protected $signature = 'test:grades';
    protected $description = 'Test grade CRUD operations';

    public function handle()
    {
        $this->info('🧪 Testing Grade System');
        $this->info(str_repeat('=', 60));
        $this->info('');
        
        // Test 1: List all grades
        $this->info('📚 Test 1: List All Grades (Ordered)');
        $grades = DB::table('grades')->orderBy('order')->get();
        $this->info("   Total Grades: {$grades->count()}");
        $this->info('');
        
        foreach ($grades as $grade) {
            $category = str_pad($grade->category ?? 'N/A', 20);
            $this->line("   {$grade->order}. [{$category}] {$grade->value} - {$grade->label}");
        }
        
        $this->info('');
        
        // Test 2: Count by category
        $this->info('📊 Test 2: Count by Category');
        $categories = ['Pre-Primary', 'Primary', 'Middle', 'Secondary', 'Senior-Secondary'];
        foreach ($categories as $cat) {
            $count = DB::table('grades')->where('category', $cat)->count();
            $this->info("   {$cat}: {$count}");
        }
        
        $this->info('');
        
        // Test 3: Get specific grade
        $this->info('🔍 Test 3: Get Specific Grade (LKG)');
        $lkg = DB::table('grades')->where('value', 'LKG')->first();
        if ($lkg) {
            $this->info("   Value: {$lkg->value}");
            $this->info("   Label: {$lkg->label}");
            $this->info("   Category: {$lkg->category}");
            $this->info("   Order: {$lkg->order}");
        }
        
        $this->info('');
        
        // Test 4: Validation test
        $this->info('✅ Test 4: Validation (exists:grades,value)');
        $testValues = ['LKG', 'UKG', 'PlaySchool', '1', '12', 'InvalidGrade'];
        foreach ($testValues as $val) {
            $exists = DB::table('grades')->where('value', $val)->exists();
            $status = $exists ? '✓ Valid' : '✗ Invalid';
            $this->line("   {$val}: {$status}");
        }
        
        $this->info('');
        $this->info(str_repeat('=', 60));
        $this->info('✅ All Tests Completed!');
        
        return 0;
    }
}

