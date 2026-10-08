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
        'teacher_first_name',
        'teacher_last_name',
        'grade',
        'section',
        'mobile',
        'father_name',
        'mother_name',
        'roll_number',
        'admission_number',
        'academic_year',
        'class_teacher_name',
        'employee_id',
        'designation',
        'department_name',
        'subjects',
        'employee_type',
        'staff_role',
        'class_teacher_grade',
        'class_teacher_section',
        'date',
        'status',
        'attendance_date',
        'class_name',
        'branch_name',
        'school_name',
        'amount_due',
        'due_date',
        'fee_type',
        'holiday_name',
        'holiday_date',
        'exam_name',
        'assignment_title',
        'assignment_titles',
        'assignment_list',
        'assignment_count',
        'subject_names',
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
