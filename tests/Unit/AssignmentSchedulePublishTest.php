<?php

namespace Tests\Unit;

use App\NotificationCampaigns\Modules\AssignmentsCampaignModule;
use PHPUnit\Framework\TestCase;

class AssignmentSchedulePublishTest extends TestCase
{
    public function test_sections_match_is_case_insensitive(): void
    {
        $this->assertTrue(AssignmentsCampaignModule::sectionsMatch('A', 'a'));
        $this->assertTrue(AssignmentsCampaignModule::sectionsMatch(' b ', 'B'));
        $this->assertFalse(AssignmentsCampaignModule::sectionsMatch('A', 'B'));
    }

    public function test_published_day_bounds_span_one_calendar_day(): void
    {
        [$start, $end] = AssignmentsCampaignModule::publishedDayBounds('2026-10-04');
        $this->assertSame('2026-10-04', $start->toDateString());
        $this->assertSame('2026-10-05', $end->toDateString());
        $this->assertTrue($start->lt($end));
    }

    public function test_late_night_utc_publish_counts_on_app_calendar_day(): void
    {
        $assignment = (object) [
            'published_at' => '2026-10-04 23:30:00',
        ];
        $this->assertSame(
            'published',
            AssignmentsCampaignModule::statusKeyForAssignmentOnDate($assignment, '2026-10-04'),
        );
    }

    public function test_assignments_for_student_matches_lowercase_section_on_assignment(): void
    {
        $date = '2026-10-04';
        $assignments = collect([
            (object) [
                'id' => 1,
                'grade' => '1',
                'section' => 'a',
                'title' => 'Reading',
                'published_at' => $date,
                'due_date' => $date,
                'subject' => (object) ['name' => 'English'],
            ],
        ]);

        $forA = AssignmentsCampaignModule::assignmentsForStudent($assignments, $date, '1', 'A');
        $this->assertCount(1, $forA);
    }
}
