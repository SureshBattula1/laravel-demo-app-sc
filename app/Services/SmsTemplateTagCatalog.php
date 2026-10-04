<?php

namespace App\Services;

/**
 * Placeholder catalog for SMS / notification templates (#tag# syntax).
 */
class SmsTemplateTagCatalog
{
    /**
     * @return array<string, array{label:string, hint:string, group:string}>
     */
    public static function tagMeta(): array
    {
        return [
            // Student (students table + user)
            'student_name' => ['label' => 'Student name', 'hint' => 'User first + last name', 'group' => 'student'],
            'grade' => ['label' => 'Grade', 'hint' => 'students.grade', 'group' => 'student'],
            'section' => ['label' => 'Section', 'hint' => 'students.section', 'group' => 'student'],
            'class_name' => ['label' => 'Class', 'hint' => 'Grade + section label', 'group' => 'student'],
            'roll_number' => ['label' => 'Roll number', 'hint' => 'students.roll_number', 'group' => 'student'],
            'admission_number' => ['label' => 'Admission number', 'hint' => 'students.admission_number', 'group' => 'student'],
            'academic_year' => ['label' => 'Academic year', 'hint' => 'students.academic_year', 'group' => 'student'],
            'class_teacher_name' => ['label' => 'Class teacher', 'hint' => 'Class teacher for grade & section', 'group' => 'student'],
            // Parents / contact
            'father_name' => ['label' => 'Father name', 'hint' => 'students.father_name', 'group' => 'parent'],
            'mother_name' => ['label' => 'Mother name', 'hint' => 'students.mother_name', 'group' => 'parent'],
            'mobile' => ['label' => 'SMS mobile', 'hint' => 'Father → mother → student phone', 'group' => 'parent'],
            // Teacher
            'teacher_name' => ['label' => 'Teacher name', 'hint' => 'Full name (first + last)', 'group' => 'teacher'],
            'teacher_first_name' => ['label' => 'Teacher first name', 'hint' => 'users.first_name', 'group' => 'teacher'],
            'teacher_last_name' => ['label' => 'Teacher last name', 'hint' => 'users.last_name', 'group' => 'teacher'],
            'employee_id' => ['label' => 'Employee ID', 'hint' => 'teachers.employee_id', 'group' => 'teacher'],
            'designation' => ['label' => 'Designation', 'hint' => 'teachers.designation', 'group' => 'teacher'],
            'department_name' => ['label' => 'Department', 'hint' => 'departments.name', 'group' => 'teacher'],
            'subjects' => ['label' => 'Subjects', 'hint' => 'Comma-separated teaching subjects', 'group' => 'teacher'],
            'employee_type' => ['label' => 'Employee type', 'hint' => 'teachers.employee_type', 'group' => 'teacher'],
            'staff_role' => ['label' => 'Staff role', 'hint' => 'users.role (non-teaching staff)', 'group' => 'teacher'],
            'class_teacher_grade' => ['label' => 'Class teacher grade', 'hint' => 'class_teacher_of_grade', 'group' => 'teacher'],
            'class_teacher_section' => ['label' => 'Class teacher section', 'hint' => 'class_teacher_of_section', 'group' => 'teacher'],
            // School / branch
            'branch_name' => ['label' => 'Branch name', 'hint' => 'branches.name', 'group' => 'school'],
            'school_name' => ['label' => 'School name', 'hint' => 'schools.name', 'group' => 'school'],
            // Dates & status
            'date' => ['label' => 'Date', 'hint' => 'Event / due date (formatted)', 'group' => 'dates'],
            'attendance_date' => ['label' => 'Attendance date', 'hint' => 'Same as event date for attendance', 'group' => 'dates'],
            'due_date' => ['label' => 'Fee due date', 'hint' => 'Fee reminder due date', 'group' => 'dates'],
            'holiday_date' => ['label' => 'Holiday date', 'hint' => 'Holiday day or range', 'group' => 'dates'],
            'status' => ['label' => 'Status', 'hint' => 'Present / absent / due / etc.', 'group' => 'status'],
            // Module-specific
            'amount_due' => ['label' => 'Amount due', 'hint' => 'Unpaid balance', 'group' => 'fees'],
            'fee_type' => ['label' => 'Fee type', 'hint' => 'Fee category name', 'group' => 'fees'],
            'holiday_name' => ['label' => 'Holiday name', 'hint' => 'Holiday title', 'group' => 'holidays'],
            'exam_name' => ['label' => 'Exam name', 'hint' => 'exams.name', 'group' => 'exams'],
            'assignment_title' => ['label' => 'Assignment title', 'hint' => 'assignments.title', 'group' => 'assignments'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function groupLabels(): array
    {
        return [
            'student' => 'Student',
            'parent' => 'Parents & contact',
            'teacher' => 'Teacher / staff',
            'school' => 'School & branch',
            'dates' => 'Dates',
            'status' => 'Status',
            'attendance' => 'Attendance',
            'fees' => 'Fees',
            'holidays' => 'Holidays',
            'exams' => 'Exams',
            'assignments' => 'Assignments',
        ];
    }

    /**
     * Tag keys per notification template type (module_type).
     *
     * @return array<string, list<string>>
     */
    public static function moduleTagKeys(): array
    {
        $teacherStaff = [
            'teacher_name', 'teacher_first_name', 'teacher_last_name',
            'employee_id', 'designation', 'department_name', 'subjects',
            'employee_type', 'staff_role', 'class_teacher_grade', 'class_teacher_section',
        ];

        $common = [
            'student_name', 'grade', 'section', 'class_name', 'roll_number',
            'admission_number', 'academic_year', 'class_teacher_name',
            'father_name', 'mother_name', 'mobile',
            'branch_name', 'school_name',
            'date', 'status',
        ];

        return [
            'attendance' => array_values(array_unique(array_merge($common, $teacherStaff, [
                'attendance_date', 'status',
            ]))),
            'holidays' => array_merge($common, [
                'holiday_name', 'holiday_date', 'date',
            ]),
            'exams' => array_merge($common, [
                'exam_name', 'date', 'attendance_date',
            ]),
            'fees' => array_merge($common, [
                'amount_due', 'due_date', 'fee_type', 'date',
            ]),
            'assignments' => array_merge($common, [
                'assignment_title', 'date',
            ]),
            'custom' => array_values(array_unique(array_merge(
                $common,
                $teacherStaff,
                ['attendance_date', 'holiday_name', 'holiday_date',
                    'exam_name', 'amount_due', 'due_date', 'fee_type', 'assignment_title']
            ))),
        ];
    }

    /**
     * @return list<string>
     */
    public static function tagsForModule(?string $moduleType): array
    {
        $moduleType = $moduleType !== null && $moduleType !== '' ? strtolower(trim($moduleType)) : 'custom';
        $map = self::moduleTagKeys();

        return $map[$moduleType] ?? $map['custom'];
    }

    /**
     * UI payload: grouped tags for one module.
     *
     * @return list<array{id:string,label:string,tags:list<array{key:string,label:string,hint:string}>}>
     */
    public static function groupedForModule(?string $moduleType): array
    {
        $meta = self::tagMeta();
        $groupLabels = self::groupLabels();
        $keys = self::tagsForModule($moduleType);
        $byGroup = [];
        foreach ($keys as $key) {
            if (! isset($meta[$key])) {
                continue;
            }
            $g = $meta[$key]['group'];
            $byGroup[$g][] = [
                'key' => $key,
                'label' => $meta[$key]['label'],
                'hint' => $meta[$key]['hint'],
            ];
        }

        $order = ['student', 'parent', 'teacher', 'school', 'dates', 'status', 'attendance', 'fees', 'holidays', 'exams', 'assignments'];
        $out = [];
        foreach ($order as $gid) {
            if (empty($byGroup[$gid])) {
                continue;
            }
            $out[] = [
                'id' => $gid,
                'label' => $groupLabels[$gid] ?? ucfirst($gid),
                'tags' => $byGroup[$gid],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, list<array{id:string,label:string,tags:list<array{key:string,label:string,hint:string}>}>>
     */
    public static function catalogByModule(): array
    {
        $out = [];
        foreach (array_keys(self::moduleTagKeys()) as $module) {
            $out[$module] = self::groupedForModule($module);
        }

        return $out;
    }

    /**
     * Flat allowlist for a module (renderer subset).
     *
     * @return list<string>
     */
    public static function flatTagsForModule(?string $moduleType): array
    {
        return self::tagsForModule($moduleType);
    }
}
