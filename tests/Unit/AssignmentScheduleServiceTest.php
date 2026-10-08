<?php

namespace Tests\Unit;

use App\NotificationCampaigns\NotificationCampaignModuleRegistry;
use App\Services\InboxNotificationService;
use App\Services\NotificationCampaignService;
use App\Services\SmsTemplateTagContextFactory;
use App\Services\SmsTemplateTagRenderer;
use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Illuminate\Support\Collection;
use Tests\CreatesApplication;
use Tests\Stubs\StubAssignmentsCampaignModule;

class AssignmentScheduleServiceTest extends LaravelTestCase
{
    use CreatesApplication;

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    public function test_assignment_summary_by_section_includes_subject_names(): void
    {
        $published = collect([
            $this->assignmentRow(1, '1', 'A', 'English HW', 'English'),
            $this->assignmentRow(2, '1', 'A', 'Hindi HW', 'Hindi'),
        ]);

        $service = $this->serviceWithPublished($published);

        $summary = $service->assignmentSummaryBySection(1, '2026-10-04');
        $this->assertArrayHasKey('1|A', $summary);
        $this->assertSame(2, $summary['1|A']['assignment_count']);
        $this->assertSame('English, Hindi', $summary['1|A']['subject_names']);
    }

    public function test_merge_delivery_adds_assignment_fields_on_rows(): void
    {
        $published = collect([
            $this->assignmentRow(1, '1', 'A', 'Reading', 'English'),
        ]);
        $service = $this->serviceWithPublished($published);

        $rows = [
            [
                'grade' => '1',
                'section' => 'A',
                'class_name' => 'Grade 1',
                'student_count' => 25,
            ],
        ];

        $delivery = ['1|A' => ['notification_status' => 'not_sent']];
        $merged = $service->mergeDeliveryIntoEligibleRows(
            'assignments',
            1,
            '2026-10-04',
            $rows,
            deliveryBySection: $delivery,
        );
        $this->assertSame(1, $merged[0]['assignment_count']);
        $this->assertSame('English', $merged[0]['subject_names']);
    }

    private function serviceWithPublished(Collection $published): NotificationCampaignService
    {
        $registry = new NotificationCampaignModuleRegistry;
        $registry->register(new StubAssignmentsCampaignModule($published));

        return new NotificationCampaignService(
            new SmsTemplateTagRenderer,
            new SmsTemplateTagContextFactory,
            $this->createMock(InboxNotificationService::class),
            $registry,
        );
    }

    private function assignmentRow(int $id, string $grade, string $section, string $title, string $subject): object
    {
        return (object) [
            'id' => $id,
            'grade' => $grade,
            'section' => $section,
            'title' => $title,
            'published_at' => Carbon::parse('2026-10-04 10:00:00'),
            'due_date' => Carbon::parse('2026-10-10'),
            'subject' => (object) ['name' => $subject],
        ];
    }
}
