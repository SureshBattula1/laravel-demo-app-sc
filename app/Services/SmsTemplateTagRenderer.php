<?php

namespace App\Services;

/**
 * Replaces #tag# placeholders using an allowlist; unknown tags become empty strings.
 */
class SmsTemplateTagRenderer
{
    /**
     * Tags supported in templates (keys match placeholder names without #).
     *
     * @var list<string>
     */
    public const ALLOWED_TAGS = [
        'student_name',
        'teacher_name',
        'grade',
        'section',
        'mobile',
        'father_name',
        'mother_name',
        'roll_number',
        'employee_id',
    ];

    /**
     * @param  array<string, string>  $context  tag key => replacement (only allowlisted keys are used)
     */
    public function render(string $body, array $context): string
    {
        $safe = [];
        foreach (self::ALLOWED_TAGS as $key) {
            $safe[$key] = (string) ($context[$key] ?? '');
        }

        return (string) preg_replace_callback(
            '/#([a-z0-9_]+)#/',
            function (array $m) use ($safe): string {
                $key = $m[1] ?? '';
                if ($key === '' || ! in_array($key, self::ALLOWED_TAGS, true)) {
                    return '';
                }

                return $safe[$key];
            },
            $body
        );
    }

    /**
     * @return list<string>
     */
    public static function allowedTags(): array
    {
        return self::ALLOWED_TAGS;
    }
}
