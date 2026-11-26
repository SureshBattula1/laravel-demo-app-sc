<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turns stored foreign-key ids in audit payloads into names for display.
 * Stored rows are left unchanged so the signature still matches.
 */
class AuditLogValueNamer
{
    /** @var array<string, array{table: string, column: string, label: string}> */
    private const TABLE_FIELDS = [
        'branch_id' => ['table' => 'branches', 'column' => 'name', 'label' => 'branch'],
        'school_id' => ['table' => 'schools', 'column' => 'name', 'label' => 'school'],
        'academic_year_id' => ['table' => 'academic_years', 'column' => 'name', 'label' => 'academic_year'],
        'grade_id' => ['table' => 'grades', 'column' => 'label', 'label' => 'grade'],
        'section_id' => ['table' => 'sections', 'column' => 'name', 'label' => 'section'],
        'subject_id' => ['table' => 'subjects', 'column' => 'name', 'label' => 'subject'],
        'class_id' => ['table' => 'classes', 'column' => 'class_name', 'label' => 'class'],
        'department_id' => ['table' => 'departments', 'column' => 'name', 'label' => 'department'],
        'exam_id' => ['table' => 'exams', 'column' => 'name', 'label' => 'exam'],
        'exam_term_id' => ['table' => 'exam_terms', 'column' => 'name', 'label' => 'exam_term'],
        'fee_type_id' => ['table' => 'fee_types', 'column' => 'name', 'label' => 'fee_type'],
        'fee_structure_id' => ['table' => 'fee_structures', 'column' => 'name', 'label' => 'fee_structure'],
        'role_id' => ['table' => 'roles', 'column' => 'name', 'label' => 'role'],
        'holiday_id' => ['table' => 'holidays', 'column' => 'name', 'label' => 'holiday'],
        'assignment_id' => ['table' => 'assignments', 'column' => 'title', 'label' => 'assignment'],
    ];

    /** @var array<string, string> */
    private const PERSON_FIELDS = [
        'user_id' => 'user',
        'student_id' => 'student',
        'teacher_id' => 'teacher',
        'class_teacher_id' => 'class_teacher',
        'created_by' => 'created_by',
        'updated_by' => 'updated_by',
        'approved_by' => 'approved_by',
        'marked_by' => 'marked_by',
        'uploaded_by' => 'uploaded_by',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function apply(array &$rows): void
    {
        if ($rows === []) {
            return;
        }

        $tableIds = [];
        $userIds = [];
        $studentIds = [];
        $teacherIds = [];
        $recordIds = [];

        foreach ($rows as $row) {
            $this->collectIds($row['old_values'] ?? null, $tableIds, $userIds, $studentIds, $teacherIds);
            $this->collectIds($row['new_values'] ?? null, $tableIds, $userIds, $studentIds, $teacherIds);
            $type = (string) ($row['auditable_type'] ?? '');
            $id = $row['auditable_id'] ?? null;
            if ($type !== '' && is_numeric($id) && empty($row['record_label'])) {
                $recordIds[$type][] = (int) $id;
            }
        }

        $tables = $this->loadTables($tableIds);
        $users = $this->loadUsers(array_merge($userIds, $studentIds, $teacherIds));
        $students = $this->loadPeople('students', $studentIds);
        $teachers = $this->loadPeople('teachers', $teacherIds);
        $records = $this->loadRecords($recordIds);

        foreach ($rows as &$row) {
            $row['old_values'] = $this->renameValues($row['old_values'] ?? null, $tables, $users, $students, $teachers);
            $row['new_values'] = $this->renameValues($row['new_values'] ?? null, $tables, $users, $students, $teachers);
            if (empty($row['record_label'])) {
                $key = ($row['auditable_type'] ?? '').':'.($row['auditable_id'] ?? '');
                if (isset($records[$key])) {
                    $row['record_label'] = $records[$key];
                }
            }
            if (! empty($row['description']) && is_string($row['description'])) {
                $row['description'] = $this->readableDescription($row['description']);
            }
        }
        unset($row);
    }

    /**
     * @param  array<string, array<int, int>>  $tableIds
     * @param  array<int, int>  $userIds
     * @param  array<int, int>  $studentIds
     * @param  array<int, int>  $teacherIds
     */
    private function collectIds(mixed $values, array &$tableIds, array &$userIds, array &$studentIds, array &$teacherIds): void
    {
        if (! is_array($values)) {
            return;
        }

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $this->collectIds($value, $tableIds, $userIds, $studentIds, $teacherIds);
                continue;
            }
            $id = $this->numericId($value);
            if ($id === null || ! is_string($key)) {
                continue;
            }
            if (isset(self::TABLE_FIELDS[$key])) {
                $tableIds[$key][] = $id;
            } elseif ($key === 'student_id') {
                $studentIds[] = $id;
            } elseif ($key === 'teacher_id' || $key === 'class_teacher_id') {
                $teacherIds[] = $id;
            } elseif (isset(self::PERSON_FIELDS[$key])) {
                $userIds[] = $id;
            }
        }
    }

    /**
     * @param  array<string, array<int, int>>  $tableIds
     * @return array<string, array<int, string>>
     */
    private function loadTables(array $tableIds): array
    {
        $names = [];
        foreach ($tableIds as $field => $ids) {
            $spec = self::TABLE_FIELDS[$field];
            $names[$field] = $this->columnNames($spec['table'], $spec['column'], $ids);
        }

        return $names;
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function columnNames(string $table, string $column, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        $rows = DB::table($table)->whereIn('id', $ids)->pluck($column, 'id');
        $out = [];
        foreach ($rows as $id => $name) {
            $label = trim((string) $name);
            if ($label !== '') {
                $out[(int) $id] = $label;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function loadUsers(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === [] || ! Schema::hasTable('users')) {
            return [];
        }

        $rows = DB::table('users')->whereIn('id', $ids)->get(['id', 'first_name', 'last_name', 'email']);
        $out = [];
        foreach ($rows as $row) {
            $name = trim(($row->first_name ?? '').' '.($row->last_name ?? ''));
            $out[(int) $row->id] = $name !== '' ? $name : (string) ($row->email ?? '');
        }

        return array_filter($out);
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function loadPeople(string $table, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, 'user_id')) {
            return [];
        }

        $links = DB::table($table)->whereIn('id', $ids)->pluck('user_id', 'id');
        $users = $this->loadUsers($links->filter()->map(fn ($id) => (int) $id)->all());
        $out = [];
        foreach ($links as $recordId => $userId) {
            if (isset($users[(int) $userId])) {
                $out[(int) $recordId] = $users[(int) $userId];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, array<int, int>>  $recordIds
     * @return array<string, string>
     */
    private function loadRecords(array $recordIds): array
    {
        $out = [];
        foreach ($recordIds as $type => $ids) {
            $names = match ($type) {
                'Student' => $this->loadPeople('students', $ids),
                'Teacher' => $this->loadPeople('teachers', $ids),
                'User', 'Profile' => $this->loadUsers($ids),
                'IdCard' => $this->idCardNames($ids),
                'Exam' => $this->columnNames('exams', 'name', $ids),
                'ExamTerm' => $this->columnNames('exam_terms', 'name', $ids),
                'ExamSchedule' => $this->columnNames('exam_schedules', 'name', $ids),
                'Holiday' => $this->columnNames('holidays', 'name', $ids),
                'Assignment' => $this->columnNames('assignments', 'title', $ids),
                'Role' => $this->columnNames('roles', 'name', $ids),
                'Branch' => $this->columnNames('branches', 'name', $ids),
                'Subject' => $this->columnNames('subjects', 'name', $ids),
                'Section' => $this->columnNames('sections', 'name', $ids),
                'Grade' => $this->columnNames('grades', 'label', $ids),
                'Department' => $this->columnNames('departments', 'name', $ids),
                'AcademicYear' => $this->columnNames('academic_years', 'name', $ids),
                'Notification', 'Communication' => $this->columnNames('notifications', 'title', $ids),
                'NotificationCampaign' => $this->columnNames('notification_campaigns', 'module', $ids),
                default => [],
            };
            foreach ($names as $id => $name) {
                $out[$type.':'.$id] = $name;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function idCardNames(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('id_cards')) {
            return [];
        }

        $cards = DB::table('id_cards')->whereIn('id', $ids)->get(['id', 'user_id', 'card_number']);
        $users = $this->loadUsers($cards->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->all());
        $out = [];
        foreach ($cards as $card) {
            $name = $users[(int) $card->user_id] ?? '';
            if ($name === '' && ! empty($card->card_number)) {
                $name = (string) $card->card_number;
            }
            if ($name !== '') {
                $out[(int) $card->id] = $name;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, array<int, string>>  $tables
     * @param  array<int, string>  $users
     * @param  array<int, string>  $students
     * @param  array<int, string>  $teachers
     * @return array<string, mixed>|null
     */
    private function renameValues(mixed $values, array $tables, array $users, array $students, array $teachers): ?array
    {
        if (! is_array($values)) {
            return null;
        }

        $out = [];
        foreach ($values as $key => $value) {
            if (! is_string($key)) {
                $out[$key] = $value;
                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->renameValues($value, $tables, $users, $students, $teachers);
                continue;
            }

            $id = $this->numericId($value);
            if ($id !== null && isset(self::TABLE_FIELDS[$key], $tables[$key][$id])) {
                $out[self::TABLE_FIELDS[$key]['label']] = $tables[$key][$id];
                continue;
            }
            if ($id !== null && isset(self::PERSON_FIELDS[$key])) {
                $name = match ($key) {
                    'student_id' => $students[$id] ?? $users[$id] ?? null,
                    'teacher_id', 'class_teacher_id' => $teachers[$id] ?? $users[$id] ?? null,
                    default => $users[$id] ?? null,
                };
                if ($name !== null && $name !== '') {
                    $out[self::PERSON_FIELDS[$key]] = $name;
                    continue;
                }
            }

            $out[$key] = $value;
        }

        return $out;
    }

    private function readableDescription(string $description): string
    {
        $description = preg_replace('/\s*\(#\d+\)/', '', $description) ?? $description;
        $description = preg_replace('/\s+#\d+\b/', '', $description) ?? $description;

        return strtr($description, [
            'branch_id' => 'branch',
            'school_id' => 'school',
            'academic_year_id' => 'academic year',
            'student_id' => 'student',
            'teacher_id' => 'teacher',
            'user_id' => 'user',
            'created_by' => 'created by',
        ]);
    }

    private function numericId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (is_string($value) && ctype_digit($value)) {
            $id = (int) $value;

            return $id > 0 ? $id : null;
        }

        return null;
    }
}
