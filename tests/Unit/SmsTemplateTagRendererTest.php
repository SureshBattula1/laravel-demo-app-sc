<?php

namespace Tests\Unit;

use App\Services\SmsTemplateTagContextFactory;
use App\Services\SmsTemplateTagRenderer;
use PHPUnit\Framework\TestCase;

class SmsTemplateTagRendererTest extends TestCase
{
    public function test_replaces_allowlisted_tags(): void
    {
        $r = new SmsTemplateTagRenderer;
        $ctx = SmsTemplateTagContextFactory::emptyTagContext();
        $ctx['student_name'] = 'A';
        $ctx['grade'] = '5';
        $out = $r->render('Hi #student_name#, grade #grade#.', $ctx);

        $this->assertSame('Hi A, grade 5.', $out);
    }

    public function test_unknown_tag_becomes_empty(): void
    {
        $r = new SmsTemplateTagRenderer;
        $ctx = SmsTemplateTagContextFactory::emptyTagContext();
        $ctx['student_name'] = 'X';
        $out = $r->render('#student_name# #not_a_tag#', $ctx);

        $this->assertSame('X ', $out);
    }
}
