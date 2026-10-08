<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomBroadcastService
{
    public function __construct(
        private AssignmentRecipientResolver $recipients,
        private NotificationCampaignService $campaigns,
    ) {}

    /**
     * @return array{
     *   title: string,
     *   description: string,
     *   optional_description: ?string,
     *   include_students: bool,
     *   include_staff: bool,
     *   student_audience_mode: string,
     *   staff_audience_mode: string,
     *   targets: list<array{grade: string, section: string}>,
     *   student_ids: list<int>,
     *   staff_user_ids: list<int>,
     *   attachments: list<array<string, mixed>>,
     *   legacy_grade: ?string,
     *   legacy_section: ?string,
     * }
     */
    public function parseAndValidate(array $input): array
    {
        $isLegacy = ! empty($input['grade']) && ! empty($input['section'])
            && ! array_key_exists('targets', $input)
            && ! array_key_exists('include_students', $input)
            && ! array_key_exists('include_staff', $input);

        if ($isLegacy) {
            $validator = Validator::make($input, [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'optional_description' => 'nullable|string',
                'grade' => 'required|string|max:50',
                'section' => 'required|string|max:50',
                'audience_mode' => 'required|in:all,custom',
                'student_ids' => 'nullable|array',
                'student_ids.*' => 'integer',
                'attachments' => 'nullable|array|max:15',
                'attachments.*.file_path' => 'required_with:attachments|string',
            ]);
            if ($validator->fails()) {
                throw ValidationException::withMessages($validator->errors()->toArray());
            }

            return [
                'title' => strip_tags((string) $input['title']),
                'description' => strip_tags((string) $input['description']),
                'optional_description' => ! empty($input['optional_description'])
                    ? strip_tags((string) $input['optional_description'])
                    : null,
                'include_students' => true,
                'include_staff' => false,
                'student_audience_mode' => (string) $input['audience_mode'],
                'staff_audience_mode' => 'all',
                'targets' => [
                    [
                        'grade' => strip_tags((string) $input['grade']),
                        'section' => strip_tags((string) $input['section']),
                    ],
                ],
                'student_ids' => collect($input['student_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all(),
                'staff_user_ids' => [],
                'attachments' => is_array($input['attachments'] ?? null) ? $input['attachments'] : [],
                'legacy_grade' => strip_tags((string) $input['grade']),
                'legacy_section' => strip_tags((string) $input['section']),
            ];
        }

        $validator = Validator::make($input, [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'optional_description' => 'nullable|string',
            'include_students' => 'required|boolean',
            'include_staff' => 'required|boolean',
            'student_audience_mode' => 'nullable|in:all,custom',
            'staff_audience_mode' => 'nullable|in:all,custom',
            'targets' => 'nullable|array',
            'targets.*.grade' => 'required_with:targets|string|max:50',
            'targets.*.section' => 'required_with:targets|string|max:50',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'integer',
            'staff_user_ids' => 'nullable|array',
            'staff_user_ids.*' => 'integer',
            'attachments' => 'nullable|array|max:15',
            'attachments.*.file_path' => 'required_with:attachments|string',
        ]);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $includeStudents = filter_var($input['include_students'], FILTER_VALIDATE_BOOLEAN);
        $includeStaff = filter_var($input['include_staff'], FILTER_VALIDATE_BOOLEAN);
        if (! $includeStudents && ! $includeStaff) {
            throw ValidationException::withMessages([
                'include_students' => ['Select students and/or branch team to notify.'],
            ]);
        }

        $targets = [];
        foreach ($input['targets'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $grade = strip_tags((string) ($row['grade'] ?? ''));
            $section = strip_tags((string) ($row['section'] ?? ''));
            if ($grade === '' || $section === '') {
                continue;
            }
            $targets[] = ['grade' => $grade, 'section' => $section];
        }

        if ($includeStudents && $targets === []) {
            throw ValidationException::withMessages([
                'targets' => ['Select at least one class section for students.'],
            ]);
        }

        $studentMode = (string) ($input['student_audience_mode'] ?? 'all');
        $staffMode = (string) ($input['staff_audience_mode'] ?? 'all');
        if ($includeStudents && ! in_array($studentMode, ['all', 'custom'], true)) {
            $studentMode = 'all';
        }
        if ($includeStaff && ! in_array($staffMode, ['all', 'custom'], true)) {
            $staffMode = 'all';
        }

        return [
            'title' => strip_tags((string) $input['title']),
            'description' => strip_tags((string) $input['description']),
            'optional_description' => ! empty($input['optional_description'])
                ? strip_tags((string) $input['optional_description'])
                : null,
            'include_students' => $includeStudents,
            'include_staff' => $includeStaff,
            'student_audience_mode' => $studentMode,
            'staff_audience_mode' => $staffMode,
            'targets' => $targets,
            'student_ids' => collect($input['student_ids'] ?? [])
                ->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all(),
            'staff_user_ids' => collect($input['staff_user_ids'] ?? [])
                ->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all(),
            'attachments' => is_array($input['attachments'] ?? null) ? $input['attachments'] : [],
            'legacy_grade' => null,
            'legacy_section' => null,
        ];
    }

    /**
     * @param  array{
     *   include_students: bool,
     *   include_staff: bool,
     *   student_audience_mode: string,
     *   staff_audience_mode: string,
     *   targets: list<array{grade: string, section: string}>,
     *   student_ids: list<int>,
     *   staff_user_ids: list<int>,
     * }  $payload
     * @return array{user_ids: list<int>, student_user_count: int, staff_user_count: int}
     */
    public function resolveRecipientUserIds(int $branchId, ?int $academicYearId, array $payload): array
    {
        $studentUserIds = [];
        if ($payload['include_students']) {
            $eligibleAcross = [];
            foreach ($payload['targets'] as $target) {
                $eligible = $this->recipients->eligibleStudentIds(
                    $branchId,
                    $target['grade'],
                    $target['section'],
                    $academicYearId
                );
                foreach ($eligible as $sid) {
                    $eligibleAcross[$sid] = true;
                }
            }
            $eligibleList = array_keys($eligibleAcross);

            if ($payload['student_audience_mode'] === 'custom') {
                $requested = $payload['student_ids'];
                if ($requested === []) {
                    throw ValidationException::withMessages([
                        'student_ids' => ['Select at least one student.'],
                    ]);
                }
                $invalid = array_values(array_diff($requested, $eligibleList));
                if ($invalid !== []) {
                    throw ValidationException::withMessages([
                        'student_ids' => ['One or more students are not in the selected class sections.'],
                    ]);
                }
                $studentIds = $requested;
            } else {
                $studentIds = $eligibleList;
            }

            $studentUserIds = $this->recipients->studentUserIds($studentIds);
        }

        $staffUserIds = [];
        if ($payload['include_staff']) {
            $allStaffIds = $this->allStaffUserIds($branchId);
            if ($payload['staff_audience_mode'] === 'custom') {
                $requested = $payload['staff_user_ids'];
                if ($requested === []) {
                    throw ValidationException::withMessages([
                        'staff_user_ids' => ['Select at least one branch team member.'],
                    ]);
                }
                $invalid = array_values(array_diff($requested, $allStaffIds));
                if ($invalid !== []) {
                    throw ValidationException::withMessages([
                        'staff_user_ids' => ['One or more staff members are invalid for this branch.'],
                    ]);
                }
                $staffUserIds = $requested;
            } else {
                $staffUserIds = $allStaffIds;
            }
        }

        $merged = array_values(array_unique(array_merge($studentUserIds, $staffUserIds)));
        if ($merged === []) {
            throw ValidationException::withMessages([
                'recipients' => ['No recipients with login accounts found for this selection.'],
            ]);
        }

        return [
            'user_ids' => $merged,
            'student_user_count' => count($studentUserIds),
            'staff_user_count' => count($staffUserIds),
        ];
    }

    /**
     * @return list<int>
     */
    public function allStaffUserIds(int $branchId): array
    {
        $payload = $this->campaigns->staffRecipientOptions($branchId, now()->toDateString());
        $ids = [];
        foreach ($payload['groups'] ?? [] as $group) {
            foreach ($group['people'] ?? [] as $person) {
                $uid = (int) ($person['user_id'] ?? 0);
                if ($uid > 0) {
                    $ids[$uid] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * @param  array{
     *   title: string,
     *   description: string,
     *   optional_description: ?string,
     *   include_students: bool,
     *   include_staff: bool,
     *   student_audience_mode: string,
     *   staff_audience_mode: string,
     *   targets: list<array{grade: string, section: string}>,
     *   student_ids: list<int>,
     *   staff_user_ids: list<int>,
     *   attachments: list<array<string, mixed>>,
     *   legacy_grade: ?string,
     *   legacy_section: ?string,
     * }  $payload
     * @return array<string, mixed>
     */
    public function buildMetadata(string $groupKey, array $payload, int $studentUserCount, int $staffUserCount): array
    {
        $firstTarget = $payload['targets'][0] ?? null;
        $grade = $payload['legacy_grade'] ?? ($firstTarget['grade'] ?? null);
        $section = $payload['legacy_section'] ?? ($firstTarget['section'] ?? null);

        $meta = [
            'source' => 'custom',
            'module' => 'custom',
            'event' => 'broadcast',
            'group_key' => $groupKey,
            'targets' => $payload['targets'],
            'include_students' => $payload['include_students'],
            'include_staff' => $payload['include_staff'],
            'student_audience_mode' => $payload['student_audience_mode'],
            'staff_audience_mode' => $payload['staff_audience_mode'],
            'student_count' => $studentUserCount,
            'staff_count' => $staffUserCount,
            'description' => $payload['description'],
            'optional_description' => $payload['optional_description'],
        ];

        if ($grade !== null && $section !== null && count($payload['targets']) === 1) {
            $meta['grade'] = $grade;
            $meta['section'] = $section;
            $meta['audience_mode'] = $payload['student_audience_mode'];
        }

        return $meta;
    }
}
