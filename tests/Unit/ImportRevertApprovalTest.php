<?php

namespace Tests\Feature;

use App\Models\ImportHistory;
use Tests\TestCase;

class ImportRevertApprovalTest extends TestCase
{
    public function test_batch_within_30_days_is_within_limit()
    {
        $batch = new ImportHistory([
            'batch_id' => 'TEST_BATCH_1',
            'status' => 'completed',
            'import_completed_at' => now()->subDays(5),
        ]);

        $this->assertTrue($batch->isWithin30Days());
    }

    public function test_batch_older_than_30_days_exceeds_limit()
    {
        $batch = new ImportHistory([
            'batch_id' => 'TEST_BATCH_2',
            'status' => 'completed',
            'import_completed_at' => now()->subDays(35),
        ]);

        $this->assertFalse($batch->isWithin30Days());
    }
}
