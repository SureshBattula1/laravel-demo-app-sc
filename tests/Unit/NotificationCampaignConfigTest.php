<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class NotificationCampaignConfigTest extends TestCase
{
    public function test_default_chunk_and_queue_keys_exist(): void
    {
        $config = require dirname(__DIR__, 2).'/config/notification_campaigns.php';

        $this->assertGreaterThan(0, $config['send_chunk_size']);
        $this->assertGreaterThan(0, $config['materialize_chunk_targets']);
        $this->assertArrayHasKey('orchestrator', $config['queues']);
        $this->assertArrayHasKey('send', $config['queues']);
    }
}
