<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SmsTemplateTagContextFactory
{
    /**
     * All allowlisted tags defaulting to empty strings (for merges and tests).
     *
     * @return array<string, string>
     */
    public static function emptyTagContext(): array
    {
        $out = [];
        foreach (SmsTemplateTagRenderer::ALLOWED_TAGS as $key) {
            $out[$key] = '';
        }

        return $out;
    }

    /**
     * @return array{branch_name:string,school_name:string}
     */
    public function branchSchoolTags(int $branchId): array
    {
        $row = DB::table('branches as b')
            ->leftJoin('schools as s', 's.id', '=', 'b.school_id')
            ->where('b.id', $branchId)
            ->first(['b.name as branch_name', 's.name as school_name']);

        return [
            'branch_name' => (string) ($row->branch_name ?? ''),
            'school_name' => (string) ($row->school_name ?? ''),
        ];
    }

    /**
     * Default SMS destination for parents/guardians: father, then mother, then student user phone.
     *
     * @return array<string, string>
     */
    public function buildForStudent(Student $student, ?int $branchId = null): array
    {
        $student->loadMissing('user');

        $user = $student->user;
        $first = trim((string) ($user->first_name ?? ''));
        $last = trim((string) ($user->last_name ?? ''));
        $studentName = trim($first.' '.$last);

        $grade = (string) ($student->grade ?? '');
        $section = (string) ($student->section ?? '');
        $className = trim($grade.' '.$section);

        $mobile = $this->resolveStudentSmsPhone($student);

        $schoolTags = $branchId !== null && $branchId > 0
            ? $this->branchSchoolTags($branchId)
            : ['branch_name' => '', 'school_name' => ''];

        return array_merge(self::emptyTagContext(), $schoolTags, [
            'student_name' => $studentName,
            'teacher_name' => '',
            'grade' => $grade,
            'section' => $section,
            'class_name' => $className,
            'mobile' => $mobile ?? '',
            'father_name' => (string) ($student->father_name ?? ''),
            'mother_name' => (string) ($student->mother_name ?? ''),
            'roll_number' => (string) ($student->roll_number ?? ''),
            'admission_number' => (string) ($student->admission_number ?? ''),
            'academic_year' => (string) ($student->academic_year ?? ''),
            'class_teacher_name' => $this->resolveClassTeacherName($student),
            'employee_id' => '',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function buildForTeacher(Teacher $teacher, ?int $branchId = null): array
    {
        $teacher->loadMissing(['user', 'department']);

        $user = $teacher->user;
        $first = trim((string) ($user->first_name ?? ''));
        $last = trim((string) ($user->last_name ?? ''));
        $teacherName = trim($first.' '.$last);

        $ext = is_array($teacher->extended_profile) ? $teacher->extended_profile : [];

        $mobile = $this->resolveTeacherSmsPhone($teacher, $ext);

        $ctGrade = (string) ($teacher->class_teacher_of_grade ?? '');
        $ctSection = (string) ($teacher->class_teacher_of_section ?? '');
        $grade = $ctGrade !== '' ? $ctGrade : 'N/A';
        $section = $ctSection !== '' ? $ctSection : 'N/A';
        $className = ($ctGrade !== '' || $ctSection !== '')
            ? trim($ctGrade.' '.$ctSection)
            : (string) ($teacher->designation ?? 'Teacher');

        $schoolTags = $branchId !== null && $branchId > 0
            ? $this->branchSchoolTags($branchId)
            : ['branch_name' => '', 'school_name' => ''];

        return array_merge(self::emptyTagContext(), $schoolTags, [
            'student_name' => '',
            'teacher_name' => $teacherName,
            'teacher_first_name' => $first,
            'teacher_last_name' => $last,
            'grade' => $grade,
            'section' => $section,
            'class_name' => $className,
            'mobile' => $mobile ?? '',
            'father_name' => (string) ($ext['father_name'] ?? ''),
            'mother_name' => (string) ($ext['mother_name'] ?? ''),
            'roll_number' => '',
            'employee_id' => (string) ($teacher->employee_id ?? ''),
            'designation' => (string) ($teacher->designation ?? ''),
            'department_name' => (string) ($teacher->department?->name ?? ''),
            'subjects' => self::formatSubjects($teacher->subjects),
            'employee_type' => (string) ($teacher->employee_type ?? ''),
            'class_teacher_grade' => $ctGrade,
            'class_teacher_section' => $ctSection,
            'staff_role' => (string) ($user->role ?? 'Teacher'),
        ]);
    }

    /**
     * Branch staff user (teacher record optional).
     *
     * @return array<string, string>
     */
    public function buildForStaffUser(User $user, ?Teacher $teacher = null, ?int $branchId = null): array
    {
        if ($teacher !== null) {
            $ctx = $this->buildForTeacher($teacher, $branchId);
            $ctx['staff_role'] = (string) ($user->role ?? $ctx['staff_role']);
            $ctx['student_name'] = $ctx['teacher_name'];

            return $ctx;
        }

        $first = trim((string) ($user->first_name ?? ''));
        $last = trim((string) ($user->last_name ?? ''));
        $name = trim($first.' '.$last);

        $schoolTags = $branchId !== null && $branchId > 0
            ? $this->branchSchoolTags($branchId)
            : ['branch_name' => '', 'school_name' => ''];

        return array_merge(self::emptyTagContext(), $schoolTags, [
            'student_name' => $name,
            'teacher_name' => $name,
            'teacher_first_name' => $first,
            'teacher_last_name' => $last,
            'mobile' => trim((string) ($user->phone ?? '')),
            'staff_role' => (string) ($user->role ?? ''),
            'designation' => (string) ($user->role ?? ''),
            'class_name' => (string) ($user->role ?? 'Staff'),
        ]);
    }

    /**
     * @param  mixed  $subjects
     */
    public static function formatSubjects($subjects): string
    {
        if (! is_array($subjects)) {
            return is_string($subjects) ? trim($subjects) : '';
        }

        $parts = [];
        foreach ($subjects as $item) {
            if (is_string($item)) {
                $v = trim($item);
                if ($v !== '') {
                    $parts[] = $v;
                }

                continue;
            }
            if (is_array($item)) {
                $v = trim((string) ($item['name'] ?? $item['subject'] ?? $item['title'] ?? ''));
                if ($v !== '') {
                    $parts[] = $v;
                }
            }
        }

        return implode(', ', $parts);
    }

    private function resolveClassTeacherName(Student $student): string
    {
        $grade = trim((string) ($student->grade ?? ''));
        $section = trim((string) ($student->section ?? ''));
        if ($grade === '' || $section === '') {
            return '';
        }

        $branchId = (int) ($student->branch_id ?? 0);
        $query = Teacher::query()
            ->where('teacher_status', 'Active')
            ->where('is_class_teacher', true)
            ->where('class_teacher_of_grade', $grade)
            ->where('class_teacher_of_section', $section)
            ->whereNotNull('user_id')
            ->with('user:id,first_name,last_name');

        if ($branchId > 0) {
            $query->where('branch_id', $branchId);
        }

        $teacher = $query->first();
        if ($teacher === null || $teacher->user === null) {
            return '';
        }

        return trim($teacher->user->first_name.' '.$teacher->user->last_name);
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
