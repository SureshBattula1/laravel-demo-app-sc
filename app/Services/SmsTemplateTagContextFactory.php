<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Teacher;

class SmsTemplateTagContextFactory
{
    /**
     * Default SMS destination for parents/guardians: father, then mother, then student user phone.
     *
     * @return array<string, string>
     */
    public function buildForStudent(Student $student): array
    {
        $student->loadMissing('user');

        $user = $student->user;
        $studentName = $user ? trim($user->first_name.' '.$user->last_name) : '';

        $mobile = $this->resolveStudentSmsPhone($student);

        return [
            'student_name' => $studentName,
            'teacher_name' => '',
            'grade' => (string) ($student->grade ?? ''),
            'section' => (string) ($student->section ?? ''),
            'mobile' => $mobile ?? '',
            'father_name' => (string) ($student->father_name ?? ''),
            'mother_name' => (string) ($student->mother_name ?? ''),
            'roll_number' => (string) ($student->roll_number ?? ''),
            'employee_id' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function buildForTeacher(Teacher $teacher): array
    {
        $teacher->loadMissing('user');

        $user = $teacher->user;
        $teacherName = $user ? trim($user->first_name.' '.$user->last_name) : '';

        $ext = is_array($teacher->extended_profile) ? $teacher->extended_profile : [];

        $mobile = $this->resolveTeacherSmsPhone($teacher, $ext);

        $grade = (string) ($teacher->class_teacher_of_grade ?? '');
        $section = (string) ($teacher->class_teacher_of_section ?? '');

        return [
            'student_name' => '',
            'teacher_name' => $teacherName,
            'grade' => $grade !== '' ? $grade : 'N/A',
            'section' => $section !== '' ? $section : 'N/A',
            'mobile' => $mobile ?? '',
            'father_name' => (string) ($ext['father_name'] ?? ''),
            'mother_name' => (string) ($ext['mother_name'] ?? ''),
            'roll_number' => '',
            'employee_id' => (string) ($teacher->employee_id ?? ''),
        ];
    }

    private function resolveStudentSmsPhone(Student $student): ?string
    {
        $candidates = [
            $student->father_phone,
            $student->mother_phone,
            $student->user?->phone,
            $student->user?->mobile,
        ];
        foreach ($candidates as $p) {
            if ($p !== null && trim((string) $p) !== '') {
                return trim((string) $p);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $extended
     */
    private function resolveTeacherSmsPhone(Teacher $teacher, array $extended): ?string
    {
        $user = $teacher->user;
        $candidates = [
            $user?->phone,
            $user?->mobile,
            $extended['alternate_phone'] ?? null,
            $extended['whatsapp_number'] ?? null,
        ];
        foreach ($candidates as $p) {
            if ($p !== null && trim((string) $p) !== '') {
                return trim((string) $p);
            }
        }

        return null;
    }
}
