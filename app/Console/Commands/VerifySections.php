<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifySections extends Command
{
    protected $signature = 'verify:sections';
    protected $description = 'Verify section creation';

    public function handle()
    {
        $this->info('📊 SECTION VERIFICATION');
        $this->info(str_repeat('=', 70));
        $this->info('');
        
        $totalSections = DB::table('sections')->count();
        $this->info("✅ Total Sections: {$totalSections}");
        $this->info('');
        
        // By Grade
        $this->info('📚 Sections by Grade:');
        $sectionsByGrade = DB::table('sections')
            ->join('grades', 'sections.grade_level', '=', 'grades.value')
            ->select('sections.grade_level', 'grades.label', 'grades.category', DB::raw('COUNT(*) as count'))
            ->groupBy('sections.grade_level', 'grades.label', 'grades.category')
            ->orderBy('grades.order')
            ->get();
        
        foreach ($sectionsByGrade as $stat) {
            $category = str_pad($stat->category ?? 'N/A', 18);
            $this->line("   [{$category}] {$stat->grade_level} ({$stat->label}): {$stat->count} sections");
        }
        
        $this->info('');
        
        // Grade 11 specific check
        $this->info('🔍 Grade 11 Sections (Detailed):');
        $grade11Sections = DB::table('sections')
            ->where('grade_level', '11')
            ->orderBy('name')
            ->get(['name', 'code', 'capacity', 'is_active']);
        
        foreach ($grade11Sections as $section) {
            $status = $section->is_active ? 'Active' : 'Inactive';
            $this->line("   Section {$section->name}: {$section->code} (Capacity: {$section->capacity}, Status: {$status})");
        }
        
        $this->info('');
        $this->info(str_repeat('=', 70));
        $this->info('✅ Verification Complete!');
        
        return 0;
    }
}

