<?php

namespace Tests\Unit;

use App\Services\SmsTemplateTagRenderer;
use PHPUnit\Framework\TestCase;

class SmsTemplateTagRendererTest extends TestCase
{
    public function test_replaces_allowlisted_tags(): void
    {
        $r = new SmsTemplateTagRenderer;
        $out = $r->render('Hi #student_name#, grade #grade#.', [
            'student_name' => 'A',
            'teacher_name' => '',
            'grade' => '5',
            'section' => '',
            'mobile' => '',
            'father_name' => '',
            'mother_name' => '',
            'roll_number' => '',
            'employee_id' => '',
        ]);

        $this->assertSame('Hi A, grade 5.', $out);
    }

    public function test_unknown_tag_becomes_empty(): void
    {
        $r = new SmsTemplateTagRenderer;
        $out = $r->render('#student_name# #not_a_tag#', [
            'student_name' => 'X',
            'teacher_name' => '',
            'grade' => '',
            'section' => '',
            'mobile' => '',
            'father_name' => '',
            'mother_name' => '',
            'roll_number' => '',
            'employee_id' => '',
        ]);

        $this->assertSame('X ', $out);
    }
}
