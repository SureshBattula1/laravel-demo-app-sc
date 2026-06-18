<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class TestGradeValidation extends Command
{
    protected $signature = 'test:grade-validation';
    protected $description = 'Test grade validation with new pre-primary grades';

    public function handle()
    {
        $this->info('🧪 Testing Grade Validation');
        $this->info(str_repeat('=', 60));
        $this->info('');
        
        // Test cases
        $testCases = [
            ['grade' => 'PlaySchool', 'expected' => true],
            ['grade' => 'Nursery', 'expected' => true],
            ['grade' => 'LKG', 'expected' => true],
            ['grade' => 'UKG', 'expected' => true],
            ['grade' => '1', 'expected' => true],
            ['grade' => '6', 'expected' => true],
            ['grade' => '12', 'expected' => true],
            ['grade' => 'InvalidGrade', 'expected' => false],
            ['grade' => '13', 'expected' => false],
            ['grade' => 'Pre-KG', 'expected' => false],
        ];
        
        $this->info('📋 Test: exists:grades,value validation');
        $this->info('');
        
        $passed = 0;
        $failed = 0;
        
        foreach ($testCases as $test) {
            $validator = Validator::make(
                ['grade' => $test['grade']],
                ['grade' => 'required|string|exists:grades,value']
            );
            
            $isValid = !$validator->fails();
            $shouldPass = $test['expected'];
            $testPassed = ($isValid === $shouldPass);
            
            $status = $testPassed ? '✅' : '❌';
            $expected = $shouldPass ? 'VALID' : 'INVALID';
            $actual = $isValid ? 'VALID' : 'INVALID';
            
            $this->line("   {$status} Grade '{$test['grade']}' - Expected: {$expected}, Got: {$actual}");
            
            if ($testPassed) {
                $passed++;
            } else {
                $failed++;
            }
        }
        
        $this->info('');
        $this->info(str_repeat('=', 60));
        
        if ($failed === 0) {
            $this->info("✅ All {$passed} validation tests PASSED!");
        } else {
            $this->error("❌ {$failed} tests FAILED, {$passed} passed");
        }
        
        $this->info('');
        
        // Test age validation
        $this->info('👶 Testing Age Validation for Pre-Primary Grades');
        $this->info('');
        
        $ageTests = [
            ['grade' => 'PlaySchool', 'age' => 2, 'expected' => true],
            ['grade' => 'PlaySchool', 'age' => 5, 'expected' => false],
            ['grade' => 'LKG', 'age' => 4, 'expected' => true],
            ['grade' => 'LKG', 'age' => 7, 'expected' => false],
            ['grade' => 'UKG', 'age' => 5, 'expected' => true],
            ['grade' => '1', 'age' => 6, 'expected' => true],
        ];
        
        foreach ($ageTests as $test) {
            $valid = $this->validateAgeForGrade($test['age'], $test['grade']);
            $testPassed = ($valid === $test['expected']);
            $status = $testPassed ? '✅' : '❌';
            $expected = $test['expected'] ? 'VALID' : 'INVALID';
            $actual = $valid ? 'VALID' : 'INVALID';
            
            $this->line("   {$status} {$test['grade']} with age {$test['age']} - Expected: {$expected}, Got: {$actual}");
        }
        
        $this->info('');
        $this->info(str_repeat('=', 60));
        $this->info('✅ Grade validation tests completed!');
        
        return 0;
    }
    
    protected function validateAgeForGrade(int $age, string $grade): bool
    {
        $gradeAgeMap = [
            'PlaySchool' => [2, 3],
            'Nursery' => [3, 4],
            'LKG' => [4, 5],
            'UKG' => [5, 6],
            '1' => [6, 7],
            '2' => [7, 8],
            '3' => [8, 9],
            '4' => [9, 10],
            '5' => [10, 11],
            '6' => [11, 12],
            '7' => [12, 13],
            '8' => [13, 14],
            '9' => [14, 15],
            '10' => [15, 16],
            '11' => [16, 17],
            '12' => [17, 18]
        ];

        if (!isset($gradeAgeMap[$grade])) {
            return true;
        }

        [$minAge, $maxAge] = $gradeAgeMap[$grade];
        return $age >= $minAge && $age <= $maxAge;
    }
}

