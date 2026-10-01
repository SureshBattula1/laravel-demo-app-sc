<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Frozen contract for GET .../notification-campaigns/marked-attendance (and attendance eligible-targets).
 */
class MarkedAttendanceResponseContractTest extends TestCase
{
    /** @var list<string> */
    public const ROW_KEYS = ['grade', 'section', 'class_name', 'student_count'];

    /** @var array<string, mixed> */
    private const SNAPSHOT_ROW = [
        'grade' => '10',
        'section' => 'A',
        'class_name' => 'Grade 10',
        'student_count' => 28,
    ];

    public function test_marked_attendance_row_shape_snapshot(): void
    {
        $this->assertSame(self::ROW_KEYS, array_keys(self::SNAPSHOT_ROW));
        $this->assertIsString(self::SNAPSHOT_ROW['grade']);
        $this->assertIsString(self::SNAPSHOT_ROW['section']);
        $this->assertIsString(self::SNAPSHOT_ROW['class_name']);
        $this->assertIsInt(self::SNAPSHOT_ROW['student_count']);
    }

    public function test_envelope_success_and_data_list(): void
    {
        $envelope = ['success' => true, 'data' => [self::SNAPSHOT_ROW]];
        $this->assertTrue($envelope['success']);
        $this->assertSame(self::ROW_KEYS, array_keys($envelope['data'][0]));
    }
}
