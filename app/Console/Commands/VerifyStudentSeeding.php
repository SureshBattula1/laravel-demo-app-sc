<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyStudentSeeding extends Command
{
    protected $signature = 'verify:students';
    protected $description = 'Verify student seeding data';

    public function handle()
    {
        $this->info('📊 STUDENT SEEDING VERIFICATION');
        $this->info(str_repeat('=', 70));
        $this->info('');
        
        // Total counts
        $totalStudents = DB::table('students')->count();
        $totalUsers = DB::table('users')->where('role', 'Student')->count();
        $studentsWithRoles = DB::table('users')
            ->where('role', 'Student')
            ->whereExists(function($query) {
                $query->select(DB::raw(1))
                    ->from('user_roles')
                    ->whereColumn('user_roles.user_id', 'users.id');
            })
            ->count();
        
        $this->info("✅ Total Students: {$totalStudents}");
        $this->info("✅ Total Student Users: {$totalUsers}");
        $this->info("✅ Students with Roles: {$studentsWithRoles}");
        $this->info('');
        
        // By Grade
        $this->info('📚 Students by Grade:');
        $gradeStats = DB::table('students')
            ->join('grades', 'students.grade', '=', 'grades.value')
            ->select('students.grade', 'grades.label', 'grades.category', DB::raw('COUNT(*) as count'))
            ->groupBy('students.grade', 'grades.label', 'grades.category')
            ->orderBy('grades.order')
            ->get();
        
        foreach ($gradeStats as $stat) {
            $category = str_pad($stat->category ?? 'N/A', 18);
            $this->line("   [{$category}] {$stat->grade} ({$stat->label}): {$stat->count} students");
        }
        
        $this->info('');
        
        // By Section
        $this->info('📋 Students by Section:');
        $sectionStats = DB::table('students')
            ->select('section', DB::raw('COUNT(*) as count'))
            ->groupBy('section')
            ->orderBy('section')
            ->get();
        
        foreach ($sectionStats as $stat) {
            $this->line("   Section {$stat->section}: {$stat->count} students");
        }
        
        $this->info('');
        
        // Gender distribution
        $this->info('👥 Gender Distribution:');
        $genderStats = DB::table('students')
            ->select('gender', DB::raw('COUNT(*) as count'))
            ->groupBy('gender')
            ->get();
        
        foreach ($genderStats as $stat) {
            $this->line("   {$stat->gender}: {$stat->count} students");
        }
        
        $this->info('');
        
        // Sample students
        $this->info('🎓 Sample Students (First 5):');
        $samples = DB::table('students')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->select('users.first_name', 'users.last_name', 'students.grade', 'students.section', 'students.admission_number')
            ->limit(5)
            ->get();
        
        foreach ($samples as $student) {
            $this->line("   {$student->first_name} {$student->last_name} - Grade {$student->grade}-{$student->section} ({$student->admission_number})");
        }
        
        $this->info('');
        $this->info(str_repeat('=', 70));
        $this->info('✅ Verification Complete!');
        
        return 0;
    }
}

