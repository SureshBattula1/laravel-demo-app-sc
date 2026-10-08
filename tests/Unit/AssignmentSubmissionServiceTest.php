<?php

namespace Tests\Unit;

use App\Models\AssignmentSubmission;
use App\Services\AssignmentSubmissionService;
use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Tests\CreatesApplication;

class AssignmentSubmissionServiceTest extends LaravelTestCase
{
    use CreatesApplication;

    private AssignmentSubmissionService $service;

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AssignmentSubmissionService;
    }

    public function test_my_status_key_pending_without_submission(): void
    {
        $this->assertSame('pending', $this->service->myStatusKey(null));
    }

    public function test_my_status_key_late_and_graded(): void
    {
        $late = new AssignmentSubmission(['status' => 'Late', 'submitted_at' => now()]);
        $graded = new AssignmentSubmission(['status' => 'Graded', 'submitted_at' => now()]);
        $this->assertSame('late', $this->service->myStatusKey($late));
        $this->assertSame('graded', $this->service->myStatusKey($graded));
    }

    public function test_present_submission_includes_complete_flag(): void
    {
        $payload = $this->service->presentSubmission(new AssignmentSubmission([
            'status' => 'Submitted',
            'submitted_at' => Carbon::parse('2026-10-04 10:00:00'),
            'submission_text' => 'Done',
        ]));
        $this->assertTrue($payload['is_complete']);
        $this->assertSame('Done', $payload['submission_text']);
    }
}
