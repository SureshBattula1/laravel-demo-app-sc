<?php

namespace Tests\Unit;

use App\NotificationCampaigns\Modules\FeesCampaignModule;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class FeesCampaignModuleTest extends TestCase
{
    public function test_fee_type_all_sentinel(): void
    {
        $this->assertSame('__all__', FeesCampaignModule::FEE_TYPE_ALL);
    }

    public function test_structure_classification_includes_enrolled_without_due_records(): void
    {
        $module = new FeesCampaignModule;
        $students = collect([
            (object) ['id' => 1, 'user_id' => 101],
            (object) ['id' => 2, 'user_id' => 102],
            (object) ['id' => 3, 'user_id' => null],
        ]);

        $map = $module->classifyByUserIdForStructure(new Collection($students));

        $this->assertSame(
            [101 => 'structure', 102 => 'structure'],
            $map
        );
    }

    public function test_section_fee_summaries_empty_without_due_date(): void
    {
        $module = new FeesCampaignModule;
        $this->assertSame([], $module->sectionFeeSummaries(1, null, null, null));
        $this->assertSame([], $module->sectionFeeSummaries(1, '', 'Tuition Fee', '2025-26'));
    }

    public function test_grade_keys_normalize_grade_prefix(): void
    {
        $module = new FeesCampaignModule;
        $normalize = (new \ReflectionMethod(FeesCampaignModule::class, 'normalizeGradeKey'))
            ->invoke($module, 'Grade 1');
        $this->assertSame('1', $normalize);
        $matches = (new \ReflectionMethod(FeesCampaignModule::class, 'gradeMatches'))
            ->invoke($module, '1', 'Grade 1');
        $this->assertTrue($matches);
    }

    public function test_order_due_dates_upcoming_first_then_past(): void
    {
        $module = new FeesCampaignModule;
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();
        $nextWeek = now()->addDays(7)->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $ordered = $module->orderDueDatesUpcomingFirst(
            [$yesterday, $nextWeek, $tomorrow, $today],
            15
        );

        $this->assertSame([$today, $tomorrow, $nextWeek, $yesterday], $ordered);
    }
}
