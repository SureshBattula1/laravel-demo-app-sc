<?php

namespace Tests\Unit;

use App\NotificationCampaigns\Modules\AssignmentsCampaignModule;
use PHPUnit\Framework\TestCase;

class AssignmentsCampaignModuleTest extends TestCase
{
    public function test_published_status_when_published_on_notification_date(): void
    {
        $assignment = (object) [
            'due_date' => '2026-10-15',
            'published_at' => '2026-10-15',
        ];

        $this->assertSame('published', AssignmentsCampaignModule::statusKeyForAssignmentOnDate($assignment, '2026-10-15'));
    }

    public function test_published_status_when_published_today_but_due_later(): void
    {
        $assignment = (object) [
            'due_date' => '2026-10-20',
            'published_at' => '2026-10-15',
        ];

        $this->assertSame('published', AssignmentsCampaignModule::statusKeyForAssignmentOnDate($assignment, '2026-10-15'));
    }

    public function test_no_status_on_due_date_only(): void
    {
        $assignment = (object) [
            'due_date' => '2026-10-15',
            'published_at' => '2026-10-01',
        ];

        $this->assertNull(AssignmentsCampaignModule::statusKeyForAssignmentOnDate($assignment, '2026-10-15'));
    }

    public function test_no_status_when_assignment_unrelated_to_date(): void
    {
        $assignment = (object) [
            'due_date' => '2026-10-20',
            'published_at' => '2026-10-01',
        ];

        $this->assertNull(AssignmentsCampaignModule::statusKeyForAssignmentOnDate($assignment, '2026-10-15'));
    }

    public function test_assignments_for_student_filters_by_section(): void
    {
        $date = '2026-10-04';
        $assignments = collect([
            $this->mockAssignment(1, '1', 'A', 'English HW', 'English', $date),
            $this->mockAssignment(2, '1', 'B', 'Hindi HW', 'Hindi', $date),
            $this->mockAssignment(3, '1', null, 'Social map', 'Social', $date),
        ]);

        $forA = AssignmentsCampaignModule::assignmentsForStudent($assignments, $date, '1', 'A');
        $this->assertCount(2, $forA);
        $this->assertSame('English HW', $forA[0]->title);
        $this->assertSame('Social map', $forA[1]->title);

        $forB = AssignmentsCampaignModule::assignmentsForStudent($assignments, $date, '1', 'B');
        $this->assertCount(2, $forB);
        $this->assertSame('Hindi HW', $forB[0]->title);
        $this->assertSame('Social map', $forB[1]->title);
    }

    public function test_build_assignment_tag_context_combines_multiple_items(): void
    {
        $date = '2026-10-04';
        $assignments = AssignmentsCampaignModule::assignmentsForStudent(
            collect([
                $this->mockAssignment(1, '1', 'A', 'Reading', 'English', $date, '2026-10-10'),
                $this->mockAssignment(2, '1', 'A', 'Worksheet', 'Hindi', $date, '2026-10-12'),
            ]),
            $date,
            '1',
            'A',
        );

        $tags = AssignmentsCampaignModule::buildAssignmentTagContext($assignments, 10, '; ');

        $this->assertSame('2', $tags['assignment_count']);
        $this->assertSame('English, Hindi', $tags['subject_names']);
        $this->assertStringContainsString('English – Reading', $tags['assignment_list']);
        $this->assertStringContainsString('Hindi – Worksheet', $tags['assignment_list']);
        $this->assertStringContainsString('English – Reading', $tags['assignment_title']);
        $this->assertStringNotContainsString('Hindi', $tags['assignment_title']);
        $this->assertSame([1, 2], $tags['assignment_ids']);
    }

    public function test_build_assignment_tag_context_includes_assignment_ids(): void
    {
        $items = collect([
            $this->mockAssignment(10, '1', 'A', 'Only', 'Math', '2026-10-04'),
        ]);
        $tags = AssignmentsCampaignModule::buildAssignmentTagContext($items, 10, '; ');
        $this->assertSame([10], $tags['assignment_ids']);
    }

    public function test_build_assignment_tag_context_truncates_when_over_max(): void
    {
        $date = '2026-10-04';
        $items = collect([
            $this->mockAssignment(1, '1', 'A', 'A1', 'Subj', $date),
            $this->mockAssignment(2, '1', 'A', 'A2', 'Subj', $date),
            $this->mockAssignment(3, '1', 'A', 'A3', 'Subj', $date),
        ]);

        $tags = AssignmentsCampaignModule::buildAssignmentTagContext($items, 2, '; ');

        $this->assertSame('3', $tags['assignment_count']);
        $this->assertStringContainsString('+ 1 more', $tags['assignment_list']);
    }

    private function mockAssignment(
        int $id,
        string $grade,
        ?string $section,
        string $title,
        string $subjectName,
        string $publishedDate,
        ?string $dueDate = null,
    ): object {
        return (object) [
            'id' => $id,
            'grade' => $grade,
            'section' => $section,
            'title' => $title,
            'published_at' => $publishedDate,
            'due_date' => $dueDate,
            'subject' => (object) ['name' => $subjectName],
        ];
    }
}
