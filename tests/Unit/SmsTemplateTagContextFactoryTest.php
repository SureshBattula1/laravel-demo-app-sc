<?php

namespace Tests\Unit;

use App\Services\SmsTemplateTagContextFactory;
use App\Services\SmsTemplateTagRenderer;
use PHPUnit\Framework\TestCase;

class SmsTemplateTagContextFactoryTest extends TestCase
{
    public function test_format_subjects_from_strings_and_objects(): void
    {
        $this->assertSame('Math, Science', SmsTemplateTagContextFactory::formatSubjects([
            'Math',
            ['name' => 'Science'],
        ]));
    }

    public function test_empty_tag_context_covers_renderer_allowlist(): void
    {
        $empty = SmsTemplateTagContextFactory::emptyTagContext();
        foreach (SmsTemplateTagRenderer::ALLOWED_TAGS as $tag) {
            $this->assertArrayHasKey($tag, $empty);
            $this->assertSame('', $empty[$tag]);
        }
    }

    public function test_renderer_replaces_teacher_professional_tags(): void
    {
        $ctx = SmsTemplateTagContextFactory::emptyTagContext();
        $ctx['teacher_name'] = 'Jane Doe';
        $ctx['employee_id'] = 'EMP-42';
        $ctx['subjects'] = 'English, History';
        $ctx['designation'] = 'Senior Teacher';

        $r = new SmsTemplateTagRenderer;
        $out = $r->render(
            'Dear #teacher_name# (#employee_id#), #designation# — #subjects#.',
            $ctx
        );

        $this->assertSame(
            'Dear Jane Doe (EMP-42), Senior Teacher — English, History.',
            $out
        );
    }
}
