<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Timetable;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TimetableController extends Controller
{
    private const VALID_DAYS = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday',
    ];

    private const DEFAULT_ACTIVE_DAYS = [
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
    ];

    /**
     * List timetable period rows (supports Advanced Search filters + optional pagination).
     */
    public function index(Request $request)
    {
        try {
            $query = Timetable::with(['branch', 'subject', 'teacher', 'creator', 'academicYear']);
            $this->applyBranchFilter($query, $request);

            if ($request->filled('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->filled('grade')) {
                $query->where('grade', $request->grade);
            }

            if ($request->filled('section')) {
                $query->where('section', $request->section);
            }

            if ($request->filled('day')) {
                $query->where('day', $request->day);
            }

            if ($request->filled('teacher_id')) {
                if ($request->teacher_id === 'unassigned' || $request->teacher_id === 'null') {
                    $query->whereNull('teacher_id');
                } else {
                    $query->where('teacher_id', $request->teacher_id);
                }
            }

            if ($request->filled('subject_id')) {
                $query->where('subject_id', $request->subject_id);
            }

            if ($request->filled('room_number')) {
                $query->where('room_number', 'like', '%'.trim((string) $request->room_number).'%');
            }

            if ($request->filled('schedule_mode')) {
                $query->where('schedule_mode', $request->schedule_mode);
            }

            if ($request->filled('is_default_weekly')) {
                $query->where('is_default_weekly', filter_var($request->is_default_weekly, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->filled('effective_date')) {
                $query->whereDate('effective_date', $request->effective_date);
            }

            if ($request->filled('from_date') && $request->filled('to_date')) {
                $query->whereBetween('effective_date', [$request->from_date, $request->to_date]);
            }

            if ($request->filled('is_active') && $request->is_active !== '') {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            } elseif (! $request->boolean('include_inactive')) {
                $query->where('is_active', true);
            }

            $searchTerm = $request->filled('q')
                ? trim((string) $request->q)
                : ($request->filled('search') ? trim((string) $request->search) : '');

            if ($searchTerm !== '') {
                $needle = '%'.$searchTerm.'%';
                $query->where(function ($qry) use ($needle) {
                    $qry->where('grade', 'like', $needle)
                        ->orWhere('section', 'like', $needle)
                        ->orWhere('day', 'like', $needle)
                        ->orWhere('room_number', 'like', $needle)
                        ->orWhereHas('subject', function ($s) use ($needle) {
                            $s->where('name', 'like', $needle)
                                ->orWhere('code', 'like', $needle);
                        })
                        ->orWhereHas('teacher', function ($t) use ($needle) {
                            $t->where('first_name', 'like', $needle)
                                ->orWhere('last_name', 'like', $needle);
                        })
                        ->orWhereHas('branch', function ($b) use ($needle) {
                            $b->where('name', 'like', $needle);
                        });
                });
            }

            if ($request->filled('academic_year') || $request->filled('academic_year_id') || $request->attributes->get('academic_year_id')) {
                [$academicYearId, $academicYearName] = $this->resolveAcademicYearFromRequest($request);
                $this->applyAcademicYearScope($query, $academicYearId, $academicYearName);
            }

            if ($request->get('group_by') === 'class') {
                return $this->respondGroupedByClass($query, $request);
            }

            $allowedSorts = [
                'grade',
                'section',
                'day_order',
                'period_number',
                'start_time',
                'duration_minutes',
                'schedule_mode',
                'effective_date',
                'is_active',
                'created_at',
            ];
            $sortBy = $request->get('sort_by');
            $sortDir = strtolower((string) $request->get('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

            if ($sortBy && in_array($sortBy, $allowedSorts, true)) {
                $query->orderBy($sortBy, $sortDir);
            } else {
                $query->orderBy('grade')
                    ->orderBy('section')
                    ->orderBy('day_order')
                    ->orderBy('period_number')
                    ->orderBy('start_time');
            }

            if ($request->filled('per_page')) {
                $perPage = max(1, min(200, (int) $request->get('per_page', 25)));
                $page = max(1, (int) $request->get('page', 1));
                $paginated = $query->paginate($perPage, ['*'], 'page', $page);

                return response()->json([
                    'success' => true,
                    'data' => $paginated->items(),
                    'meta' => [
                        'current_page' => $paginated->currentPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                        'last_page' => $paginated->lastPage(),
                        'from' => $paginated->firstItem(),
                        'to' => $paginated->lastItem(),
                        'has_more_pages' => $paginated->hasMorePages(),
                    ],
                    'message' => 'Timetables retrieved successfully',
                ]);
            }

            $timetables = $query->get();

            return response()->json([
                'success' => true,
                'data' => $timetables,
                'message' => 'Timetables retrieved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Get timetables error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch timetables',

            ], 500);
        }
    }

    /**
     * Store single timetable period row.
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $normalized = $this->normalizeSinglePayload($request->all());

            $validator = Validator::make($normalized, [
                'branch_id' => 'required|exists:branches,id',
                'grade' => 'required|string|max:50',
                'section' => 'required|string|max:10',
                'subject_id' => 'required|exists:subjects,id',
                'teacher_id' => 'nullable|integer',
                'schedule_mode' => 'required|string|in:weekly,daily',
                'is_default_weekly' => 'boolean',
                'effective_date' => 'required_if:schedule_mode,daily|nullable|date',
                'active_days' => 'nullable|array',
                'active_days.*' => 'string|in:'.implode(',', self::VALID_DAYS),
                'day' => 'required|string|in:'.implode(',', self::VALID_DAYS),
                'day_order' => 'required|integer|min:1|max:7',
                'period_number' => 'required|integer|min:1|max:20',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'duration_minutes' => 'required|integer|min:5|max:480',
                'room_number' => 'nullable|string|max:50',
                'academic_year' => 'nullable|string|max:20',
                'academic_year_id' => 'nullable|exists:academic_years,id',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            if (! $this->canManageBranch($request, (int) $normalized['branch_id'])) {
                DB::rollBack();

                return $this->forbiddenResponse('You do not have access to this branch');
            }

            [$academicYearId, $academicYearName] = $this->resolveAcademicYearFromRequest($request);
            $academicYearName = $academicYearName ?? ($normalized['academic_year'] ?? date('Y'));

            $conflictResponse = $this->detectConflict(
                branchId: (int) $normalized['branch_id'],
                grade: (string) $normalized['grade'],
                section: (string) $normalized['section'],
                academicYearId: $academicYearId,
                academicYearName: $academicYearName,
                scheduleMode: $normalized['schedule_mode'],
                effectiveDate: $normalized['effective_date'],
                day: $normalized['day'],
                periodNumber: (int) $normalized['period_number'],
                teacherId: ! empty($normalized['teacher_id']) ? (int) $normalized['teacher_id'] : null,
                ignoreIds: []
            );

            if ($conflictResponse !== null) {
                DB::rollBack();

                return response()->json($conflictResponse, 422);
            }

            $branch = Branch::find($normalized['branch_id']);
            $timetable = Timetable::create([
                'branch_id' => $normalized['branch_id'],
                'school_id' => $branch?->school_id,
                'grade' => $normalized['grade'],
                'grade_level' => $normalized['grade'],
                'section' => $normalized['section'],
                'subject_id' => $normalized['subject_id'],
                'teacher_id' => $normalized['teacher_id'] ?: null,
                'day' => $normalized['day'],
                'day_order' => $normalized['day_order'],
                'period_number' => $normalized['period_number'],
                'start_time' => $normalized['start_time'],
                'end_time' => $normalized['end_time'],
                'duration_minutes' => $normalized['duration_minutes'],
                'room_number' => $normalized['room_number'] ?: null,
                'schedule_mode' => $normalized['schedule_mode'],
                'is_default_weekly' => $normalized['is_default_weekly'],
                'effective_date' => $normalized['effective_date'],
                'active_days' => $normalized['active_days'],
                'academic_year' => $academicYearName,
                'academic_year_id' => $academicYearId,
                'is_active' => $normalized['is_active'] ?? true,
                'created_by' => $request->user()->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Timetable created successfully',
                'data' => $timetable->load(['branch', 'subject', 'teacher', 'academicYear']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create timetable error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create timetable',

            ], 500);
        }
    }

    /**
     * Bulk create or update weekly template or day-to-day timetable periods for a class/section.
     */
    public function bulkUpsert(Request $request)
    {
        DB::beginTransaction();
        try {
            $headerValidator = Validator::make($request->all(), [
                'branch_id' => 'required|exists:branches,id',
                'grade' => 'required|string|max:50',
                'section' => 'required|string|max:10',
                'academic_year' => 'nullable|string|max:20',
                'academic_year_id' => 'nullable|exists:academic_years,id',
                'schedule_mode' => 'nullable|string|in:weekly,daily',
                'is_default_weekly' => 'nullable|boolean',
                'effective_date' => 'required_if:schedule_mode,daily|nullable|date',
                'active_days' => 'nullable|array',
                'active_days.*' => 'string|in:'.implode(',', self::VALID_DAYS),
                'replace_scope' => 'nullable|boolean',
                'days_in_scope' => 'nullable|array',
                'days_in_scope.*' => 'string|in:'.implode(',', self::VALID_DAYS),
                'periods' => 'required|array|min:1',
            ]);

            if ($headerValidator->fails()) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => $headerValidator->errors()->first(),
                    'errors' => $headerValidator->errors(),
                ], 422);
            }

            $branchId = (int) $request->branch_id;
            if (! $this->canManageBranch($request, $branchId)) {
                DB::rollBack();

                return $this->forbiddenResponse('You do not have access to this branch');
            }

            $grade = trim((string) $request->grade);
            $section = trim((string) $request->section);
            $scheduleMode = $request->input('schedule_mode', 'weekly') === 'daily' ? 'daily' : 'weekly';
            $effectiveDate = $scheduleMode === 'daily'
                ? Carbon::parse((string) $request->effective_date)->format('Y-m-d')
                : null;
            $isDefaultWeekly = $scheduleMode === 'weekly'
                ? (bool) $request->input('is_default_weekly', true)
                : false;
            $activeDays = $scheduleMode === 'weekly'
                ? ($request->input('active_days') ?: self::DEFAULT_ACTIVE_DAYS)
                : null;
            $replaceScope = $request->boolean('replace_scope', true);

            [$academicYearId, $academicYearName] = $this->resolveAcademicYearFromRequest($request);
            $academicYearName = $academicYearName ?? ($request->input('academic_year') ?: date('Y'));

            $normalizedPeriods = [];
            $seenSlotKeys = [];

            foreach ($request->input('periods', []) as $idx => $rawRow) {
                $merged = array_merge($rawRow, [
                    'branch_id' => $branchId,
                    'grade' => $grade,
                    'section' => $section,
                    'schedule_mode' => $scheduleMode,
                    'is_default_weekly' => $isDefaultWeekly,
                    'effective_date' => $effectiveDate,
                    'active_days' => $activeDays,
                ]);
                $norm = $this->normalizeSinglePayload($merged);

                $rowValidator = Validator::make($norm, [
                    'id' => 'nullable|integer',
                    'subject_id' => 'required|exists:subjects,id',
                    'teacher_id' => 'nullable|integer',
                    'day' => 'required|string|in:'.implode(',', self::VALID_DAYS),
                    'day_order' => 'required|integer|min:1|max:7',
                    'period_number' => 'required|integer|min:1|max:20',
                    'start_time' => 'required|date_format:H:i',
                    'end_time' => 'required|date_format:H:i|after:start_time',
                    'duration_minutes' => 'required|integer|min:5|max:480',
                    'room_number' => 'nullable|string|max:50',
                    'is_active' => 'boolean',
                ]);

                if ($rowValidator->fails()) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Row '.($idx + 1).': '.$rowValidator->errors()->first(),
                        'errors' => $rowValidator->errors(),
                        'row_index' => $idx,
                    ], 422);
                }

                // Skip periods on days unchecked in active_days when in weekly mode
                if ($scheduleMode === 'weekly' && is_array($activeDays) && ! in_array($norm['day'], $activeDays, true)) {
                    continue;
                }

                $slotKey = $norm['day'].'#'.$norm['period_number'];
                if (isset($seenSlotKeys[$slotKey])) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => "Duplicate slot in request: {$norm['day']} Period {$norm['period_number']}",
                        'conflict_type' => 'duplicate_request_slot',
                    ], 422);
                }
                $seenSlotKeys[$slotKey] = true;
                $normalizedPeriods[] = $norm;
            }

            if (empty($normalizedPeriods)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'At least one period on an active day is required.',
                ], 422);
            }

            // Existing rows in this class scope
            $scopeQuery = Timetable::where('branch_id', $branchId)
                ->where('grade', $grade)
                ->where('section', $section)
                ->where('schedule_mode', $scheduleMode);

            $this->applyAcademicYearScope($scopeQuery, $academicYearId, $academicYearName);

            if ($scheduleMode === 'daily') {
                $scopeQuery->whereDate('effective_date', $effectiveDate);
            } elseif ($request->filled('days_in_scope') && is_array($request->days_in_scope)) {
                $scopeQuery->whereIn('day', $request->days_in_scope);
            }

            $existingScopeRows = $scopeQuery->get();
            $existingIdsInScope = $existingScopeRows->pluck('id')->all();

            // Check conflicts (against rows outside the replaced scope, or all rows if replace_scope=false)
            foreach ($normalizedPeriods as $idx => $norm) {
                $ignoreIds = $replaceScope
                    ? $existingIdsInScope
                    : (! empty($norm['id']) ? [(int) $norm['id']] : []);

                $conflict = $this->detectConflict(
                    branchId: $branchId,
                    grade: $grade,
                    section: $section,
                    academicYearId: $academicYearId,
                    academicYearName: $academicYearName,
                    scheduleMode: $scheduleMode,
                    effectiveDate: $effectiveDate,
                    day: $norm['day'],
                    periodNumber: (int) $norm['period_number'],
                    teacherId: ! empty($norm['teacher_id']) ? (int) $norm['teacher_id'] : null,
                    ignoreIds: $ignoreIds
                );

                if ($conflict !== null) {
                    DB::rollBack();
                    $conflict['row_index'] = $idx;

                    return response()->json($conflict, 422);
                }
            }

            $branch = Branch::find($branchId);
            $actorId = $request->user()->id;

            $existingBySlot = $existingScopeRows->keyBy(fn (Timetable $t) => $t->day.'#'.$t->period_number);
            $existingById = $existingScopeRows->keyBy('id');

            $keptIds = [];
            $savedModels = [];
            $createdCount = 0;
            $updatedCount = 0;

            foreach ($normalizedPeriods as $norm) {
                $slotKey = $norm['day'].'#'.$norm['period_number'];
                $target = null;

                if (! empty($norm['id']) && $existingById->has((int) $norm['id'])) {
                    $target = $existingById->get((int) $norm['id']);
                } elseif ($existingBySlot->has($slotKey)) {
                    $target = $existingBySlot->get($slotKey);
                }

                $attributes = [
                    'branch_id' => $branchId,
                    'school_id' => $branch?->school_id,
                    'grade' => $grade,
                    'grade_level' => $grade,
                    'section' => $section,
                    'subject_id' => $norm['subject_id'],
                    'teacher_id' => $norm['teacher_id'] ?: null,
                    'day' => $norm['day'],
                    'day_order' => $norm['day_order'],
                    'period_number' => $norm['period_number'],
                    'start_time' => $norm['start_time'],
                    'end_time' => $norm['end_time'],
                    'duration_minutes' => $norm['duration_minutes'],
                    'room_number' => $norm['room_number'] ?: null,
                    'schedule_mode' => $scheduleMode,
                    'is_default_weekly' => $isDefaultWeekly,
                    'effective_date' => $effectiveDate,
                    'active_days' => $activeDays,
                    'academic_year' => $academicYearName,
                    'academic_year_id' => $academicYearId,
                    'is_active' => $norm['is_active'] ?? true,
                ];

                if ($target) {
                    $attributes['updated_by'] = $actorId;
                    $target->update($attributes);
                    $keptIds[] = $target->id;
                    $savedModels[] = $target->fresh(['branch', 'subject', 'teacher', 'academicYear']);
                    $updatedCount++;
                } else {
                    $attributes['created_by'] = $actorId;
                    $created = Timetable::create($attributes);
                    $keptIds[] = $created->id;
                    $savedModels[] = $created->load(['branch', 'subject', 'teacher', 'academicYear']);
                    $createdCount++;
                }
            }

            $deletedCount = 0;
            if ($replaceScope) {
                $idsToDelete = array_values(array_diff($existingIdsInScope, $keptIds));
                if (! empty($idsToDelete)) {
                    $deletedCount = Timetable::whereIn('id', $idsToDelete)->delete();
                }

                // Keep active_days consistent across remaining weekly rows of this class
                if ($scheduleMode === 'weekly' && is_array($activeDays)) {
                    $syncQuery = Timetable::where('branch_id', $branchId)
                        ->where('grade', $grade)
                        ->where('section', $section)
                        ->where('schedule_mode', 'weekly');
                    $this->applyAcademicYearScope($syncQuery, $academicYearId, $academicYearName);
                    $syncQuery->update([
                        'active_days' => json_encode($activeDays),
                        'is_default_weekly' => $isDefaultWeekly,
                    ]);

                    // Prune periods on unchecked inactive days when full weekly save (no partial days_in_scope)
                    if (! $request->filled('days_in_scope')) {
                        $pruneQuery = Timetable::where('branch_id', $branchId)
                            ->where('grade', $grade)
                            ->where('section', $section)
                            ->where('schedule_mode', 'weekly')
                            ->whereNotIn('day', $activeDays);
                        $this->applyAcademicYearScope($pruneQuery, $academicYearId, $academicYearName);
                        $deletedCount += $pruneQuery->delete();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Timetable saved successfully',
                'data' => $savedModels,
                'summary' => [
                    'saved_count' => count($savedModels),
                    'created' => $createdCount,
                    'updated' => $updatedCount,
                    'deleted' => $deletedCount,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk upsert timetable error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save timetable schedule',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Get specific timetable row.
     */
    public function show(string $id)
    {
        try {
            $timetable = Timetable::with(['branch', 'subject', 'teacher', 'creator', 'academicYear'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $timetable,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Timetable not found',
            ], 404);
        }
    }

    /**
     * Update single timetable period row.
     */
    public function update(Request $request, string $id)
    {
        DB::beginTransaction();
        try {
            $timetable = Timetable::findOrFail($id);
            if (! $this->canManageBranch($request, (int) $timetable->branch_id)) {
                DB::rollBack();

                return $this->forbiddenResponse('You do not have access to this branch');
            }

            // Fast-path toggle for is_active only
            $inputKeys = array_keys($request->all());
            if ($inputKeys === ['is_active']) {
                $timetable->update([
                    'is_active' => $request->boolean('is_active'),
                    'updated_by' => $request->user()->id,
                ]);
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Timetable status updated successfully',
                    'data' => $timetable->load(['branch', 'subject', 'teacher', 'academicYear']),
                ]);
            }

            $mergedInput = array_merge([
                'branch_id' => $timetable->branch_id,
                'grade' => $timetable->grade,
                'section' => $timetable->section,
                'subject_id' => $timetable->subject_id,
                'teacher_id' => $timetable->teacher_id,
                'day' => $timetable->day,
                'period_number' => $timetable->period_number,
                'start_time' => $timetable->start_time,
                'end_time' => $timetable->end_time,
                'duration_minutes' => $timetable->duration_minutes,
                'room_number' => $timetable->room_number,
                'schedule_mode' => $timetable->schedule_mode ?? 'weekly',
                'is_default_weekly' => $timetable->is_default_weekly ?? true,
                'effective_date' => $timetable->effective_date?->format('Y-m-d'),
                'active_days' => $timetable->active_days,
                'is_active' => $timetable->is_active,
            ], $request->all());

            // Allow explicit clearing of optional fields when key is present in request
            if ($request->exists('teacher_id') && ($request->input('teacher_id') === '' || $request->input('teacher_id') === null)) {
                $mergedInput['teacher_id'] = null;
            }
            if ($request->exists('room_number') && ($request->input('room_number') === '' || $request->input('room_number') === null)) {
                $mergedInput['room_number'] = null;
            }

            $normalized = $this->normalizeSinglePayload($mergedInput);

            $validator = Validator::make($normalized, [
                'branch_id' => 'required|exists:branches,id',
                'grade' => 'required|string|max:50',
                'section' => 'required|string|max:10',
                'subject_id' => 'required|exists:subjects,id',
                'teacher_id' => 'nullable|integer',
                'schedule_mode' => 'required|string|in:weekly,daily',
                'is_default_weekly' => 'boolean',
                'effective_date' => 'required_if:schedule_mode,daily|nullable|date',
                'active_days' => 'nullable|array',
                'active_days.*' => 'string|in:'.implode(',', self::VALID_DAYS),
                'day' => 'required|string|in:'.implode(',', self::VALID_DAYS),
                'day_order' => 'required|integer|min:1|max:7',
                'period_number' => 'required|integer|min:1|max:20',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'duration_minutes' => 'required|integer|min:5|max:480',
                'room_number' => 'nullable|string|max:50',
                'is_active' => 'boolean',
            ]);

            if ($validator->fails()) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            if ((int) $normalized['branch_id'] !== (int) $timetable->branch_id && ! $this->canManageBranch($request, (int) $normalized['branch_id'])) {
                DB::rollBack();

                return $this->forbiddenResponse('You do not have access to the target branch');
            }

            $academicYearId = $timetable->academic_year_id;
            $academicYearName = $timetable->academic_year;
            if ($request->filled('academic_year') || $request->filled('academic_year_id')) {
                [$resolvedYearId, $resolvedYearName] = $this->resolveAcademicYearFromRequest($request);
                $academicYearId = $resolvedYearId ?? $academicYearId;
                $academicYearName = $resolvedYearName ?? $academicYearName;
            }

            $conflictResponse = $this->detectConflict(
                branchId: (int) $normalized['branch_id'],
                grade: (string) $normalized['grade'],
                section: (string) $normalized['section'],
                academicYearId: $academicYearId,
                academicYearName: $academicYearName,
                scheduleMode: $normalized['schedule_mode'],
                effectiveDate: $normalized['effective_date'],
                day: $normalized['day'],
                periodNumber: (int) $normalized['period_number'],
                teacherId: ! empty($normalized['teacher_id']) ? (int) $normalized['teacher_id'] : null,
                ignoreIds: [(int) $timetable->id]
            );

            if ($conflictResponse !== null) {
                DB::rollBack();

                return response()->json($conflictResponse, 422);
            }

            $timetable->update([
                'branch_id' => $normalized['branch_id'],
                'grade' => $normalized['grade'],
                'grade_level' => $normalized['grade'],
                'section' => $normalized['section'],
                'subject_id' => $normalized['subject_id'],
                'teacher_id' => $normalized['teacher_id'] ?: null,
                'day' => $normalized['day'],
                'day_order' => $normalized['day_order'],
                'period_number' => $normalized['period_number'],
                'start_time' => $normalized['start_time'],
                'end_time' => $normalized['end_time'],
                'duration_minutes' => $normalized['duration_minutes'],
                'room_number' => $normalized['room_number'] ?: null,
                'schedule_mode' => $normalized['schedule_mode'],
                'is_default_weekly' => $normalized['is_default_weekly'],
                'effective_date' => $normalized['effective_date'],
                'active_days' => $normalized['active_days'],
                'academic_year_id' => $academicYearId,
                'academic_year' => $academicYearName,
                'is_active' => $normalized['is_active'] ?? true,
                'updated_by' => $request->user()->id,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Timetable updated successfully',
                'data' => $timetable->load(['branch', 'subject', 'teacher', 'academicYear']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update timetable error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update timetable',

            ], 500);
        }
    }

    /**
     * Delete timetable row.
     */
    public function destroy(Request $request, string $id)
    {
        DB::beginTransaction();
        try {
            $timetable = Timetable::findOrFail($id);
            if (! $this->canManageBranch($request, (int) $timetable->branch_id)) {
                DB::rollBack();

                return $this->forbiddenResponse('You do not have access to this branch');
            }
            $timetable->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Timetable deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete timetable error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete timetable',

            ], 500);
        }
    }

    /**
     * Get class timetable (weekly template + optional effective date overlay for day-to-day overrides).
     */
    public function getByClass(Request $request, string $grade, string $section)
    {
        try {
            [$academicYearId, $academicYearName] = $this->resolveAcademicYearFromRequest($request);
            $academicYear = $request->get('academic_year', $academicYearName ?? date('Y'));
            $targetDate = $request->filled('date')
                ? Carbon::parse((string) $request->date)->format('Y-m-d')
                : null;

            $baseQuery = Timetable::with(['branch', 'subject', 'teacher'])
                ->where('grade', $grade)
                ->where('section', $section);

            $this->applyBranchFilter($baseQuery, $request);
            if ($request->filled('branch_id')) {
                $baseQuery->where('branch_id', $request->branch_id);
            }
            if (! $request->boolean('include_inactive')) {
                $baseQuery->where('is_active', true);
            }
            $this->applyAcademicYearScope($baseQuery, $academicYearId, $academicYear);

            $weeklyRows = (clone $baseQuery)
                ->where(function ($q) {
                    $q->where('schedule_mode', 'weekly')
                        ->orWhereNull('schedule_mode');
                })
                ->orderBy('day_order')
                ->orderBy('period_number')
                ->get();

            $activeDays = $weeklyRows->pluck('active_days')->filter()->first()
                ?? ($weeklyRows->isNotEmpty()
                    ? $weeklyRows->pluck('day')->unique()->values()->all()
                    : self::DEFAULT_ACTIVE_DAYS);

            $grouped = $weeklyRows->groupBy('day')->map(function ($daySchedule) {
                return $daySchedule->sortBy('period_number')->values();
            });

            $dailyOverrides = collect();
            $overrideWeekday = null;

            if ($targetDate !== null) {
                $overrideWeekday = Carbon::parse($targetDate)->format('l');
                $dailyOverrides = (clone $baseQuery)
                    ->where('schedule_mode', 'daily')
                    ->whereDate('effective_date', $targetDate)
                    ->orderBy('period_number')
                    ->get();

                if ($dailyOverrides->isNotEmpty()) {
                    $grouped->put($overrideWeekday, $dailyOverrides->values());
                }
            }

            // Also return daily overrides indexed by YYYY-MM-DD for Calendar Day / Week / Month views
            $rangeQuery = (clone $baseQuery)
                ->where('schedule_mode', 'daily')
                ->whereNotNull('effective_date')
                ->orderBy('effective_date')
                ->orderBy('period_number');

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $rangeQuery->whereBetween('effective_date', [
                    Carbon::parse((string) $request->start_date)->format('Y-m-d'),
                    Carbon::parse((string) $request->end_date)->format('Y-m-d'),
                ]);
            }

            $dailyOverridesByDate = $rangeQuery->get()
                ->groupBy(fn (Timetable $row) => $row->effective_date?->format('Y-m-d') ?? '')
                ->filter(fn ($rows, $key) => $key !== '')
                ->map(fn ($rows) => $rows->sortBy('period_number')->values());

            $sampleBranch = $weeklyRows->first()?->branch
                ?? ($request->filled('branch_id') ? Branch::find($request->branch_id) : null);

            return response()->json([
                'success' => true,
                'data' => [
                    'grade' => $grade,
                    'section' => $section,
                    'branch_id' => $sampleBranch?->id,
                    'branch' => $sampleBranch ? [
                        'id' => $sampleBranch->id,
                        'name' => $sampleBranch->name,
                        'code' => $sampleBranch->code,
                    ] : null,
                    'academic_year' => $academicYear,
                    'academic_year_id' => $academicYearId,
                    'active_days' => $activeDays,
                    'effective_date' => $targetDate,
                    'override_weekday' => $overrideWeekday,
                    'has_daily_override' => $dailyOverrides->isNotEmpty(),
                    'schedule' => $grouped,
                    'daily_overrides_by_date' => $dailyOverridesByDate,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get class timetable error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch class timetable',

            ], 500);
        }
    }

    /**
     * Delete all timetable slots for a specific class (grade + section).
     */
    public function destroyClass(Request $request, string $grade, string $section)
    {
        DB::beginTransaction();
        try {
            $query = Timetable::where('grade', $grade)->where('section', $section);
            $this->applyBranchFilter($query, $request);
            if ($request->filled('branch_id')) {
                if (! $this->canManageBranch($request, (int) $request->branch_id)) {
                    DB::rollBack();

                    return $this->forbiddenResponse('You do not have access to this branch');
                }
                $query->where('branch_id', $request->branch_id);
            }
            if ($request->filled('academic_year') || $request->filled('academic_year_id') || $request->attributes->get('academic_year_id')) {
                [$academicYearId, $academicYearName] = $this->resolveAcademicYearFromRequest($request);
                $this->applyAcademicYearScope($query, $academicYearId, $academicYearName);
            }

            $deleted = $query->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Deleted {$deleted} timetable period(s) for Grade {$grade}-{$section}",
                'deleted_count' => $deleted,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete class timetable error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete class timetable',
            ], 500);
        }
    }

    /**
     * Aggregate filtered timetable slots into one summary row per Class-Section (e.g. Grade 1-A, Grade 1-B).
     */
    private function respondGroupedByClass($query, Request $request)
    {
        $rows = $query->get();

        $grouped = $rows->groupBy(function (Timetable $t) {
            return $t->branch_id.'|'.trim((string) $t->grade).'|'.trim((string) $t->section).'|'.($t->academic_year_id ?? $t->academic_year ?? '');
        })->map(function ($slots) {
            /** @var Timetable $first */
            $first = $slots->first();
            $weeklySlots = $slots->filter(fn (Timetable $s) => ($s->schedule_mode ?? 'weekly') !== 'daily');
            $dailySlots = $slots->filter(fn (Timetable $s) => ($s->schedule_mode ?? 'weekly') === 'daily');

            $activeDays = $weeklySlots->pluck('active_days')->filter()->first()
                ?? ($weeklySlots->isNotEmpty()
                    ? $weeklySlots->pluck('day')->unique()->values()->all()
                    : self::DEFAULT_ACTIVE_DAYS);

            $dailyDatesCount = $dailySlots
                ->map(fn (Timetable $s) => $s->effective_date?->format('Y-m-d'))
                ->filter()
                ->unique()
                ->count();

            $roomNumber = $slots->pluck('room_number')->filter()->first();
            $hasActive = $slots->contains(fn (Timetable $s) => (bool) $s->is_active);

            return [
                'id' => $first->id,
                'branch_id' => $first->branch_id,
                'branch' => $first->branch ? [
                    'id' => $first->branch->id,
                    'name' => $first->branch->name,
                    'code' => $first->branch->code,
                ] : null,
                'grade' => $first->grade,
                'section' => $first->section,
                'academic_year_id' => $first->academic_year_id,
                'academic_year' => $first->academicYear?->name ?? $first->academic_year,
                'active_days' => array_values((array) $activeDays),
                'weekly_periods_count' => $weeklySlots->count(),
                'daily_overrides_count' => $dailyDatesCount,
                'subjects_count' => $slots->pluck('subject_id')->filter()->unique()->count(),
                'teachers_count' => $slots->pluck('teacher_id')->filter()->unique()->count(),
                'room_number' => $roomNumber,
                'is_active' => $hasActive,
                'updated_at' => $slots->max('updated_at'),
            ];
        })->values();

        $sortBy = (string) $request->get('sort_by', '');
        $sortDesc = strtolower((string) $request->get('sort_direction', $request->get('sort_dir', 'asc'))) === 'desc';

        $sorted = $grouped->sort(function (array $a, array $b) use ($sortBy, $sortDesc) {
            $cmp = match ($sortBy) {
                'branch_id', 'branch_label' => strcasecmp((string) ($a['branch']['name'] ?? ''), (string) ($b['branch']['name'] ?? '')),
                'section' => strnatcasecmp((string) $a['section'], (string) $b['section']),
                'weekly_periods_count' => ($a['weekly_periods_count'] <=> $b['weekly_periods_count']),
                'subjects_count' => ($a['subjects_count'] <=> $b['subjects_count']),
                'room_number' => strnatcasecmp((string) ($a['room_number'] ?? ''), (string) ($b['room_number'] ?? '')),
                'is_active' => ((int) $a['is_active'] <=> (int) $b['is_active']),
                default => strnatcasecmp((string) $a['grade'], (string) $b['grade'])
                    ?: strnatcasecmp((string) $a['section'], (string) $b['section']),
            };

            return $sortDesc ? -$cmp : $cmp;
        })->values();

        $total = $sorted->count();
        $perPage = max(1, min(200, (int) $request->get('per_page', 25)));
        $page = max(1, (int) $request->get('page', 1));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $items = $sorted->forPage($page, $perPage)->values()->all();
        $from = $total > 0 ? (($page - 1) * $perPage) + 1 : null;
        $to = $total > 0 ? min($total, $page * $perPage) : null;

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $from,
                'to' => $to,
                'has_more_pages' => $page < $lastPage,
            ],
        ]);
    }

    /**
     * Normalize time strings, auto-compute duration_minutes / end_time, and sync schedule_mode & effective_date.
     */
    private function normalizeSinglePayload(array $input): array
    {
        $scheduleMode = ($input['schedule_mode'] ?? 'weekly') === 'daily' ? 'daily' : 'weekly';
        $effectiveDate = null;
        $day = isset($input['day']) ? ucfirst(strtolower(trim((string) $input['day']))) : '';

        if ($scheduleMode === 'daily' && ! empty($input['effective_date'])) {
            $parsedDate = Carbon::parse((string) $input['effective_date']);
            $effectiveDate = $parsedDate->format('Y-m-d');
            $day = $parsedDate->format('l');
        }

        $isDefaultWeekly = $scheduleMode === 'weekly'
            ? (array_key_exists('is_default_weekly', $input)
                ? filter_var($input['is_default_weekly'], FILTER_VALIDATE_BOOLEAN)
                : true)
            : false;

        $startTime = $this->normalizeTime($input['start_time'] ?? null);
        $endTime = $this->normalizeTime($input['end_time'] ?? null);
        $durationMinutes = isset($input['duration_minutes']) && $input['duration_minutes'] !== '' && $input['duration_minutes'] !== null
            ? (int) $input['duration_minutes']
            : null;

        if ($startTime && ! $endTime && $durationMinutes && $durationMinutes > 0) {
            $endTime = $this->addMinutesToTime($startTime, $durationMinutes);
        }

        if ($startTime && $endTime) {
            $calculated = Timetable::calculateDurationMinutes($startTime, $endTime);
            if ($calculated !== null) {
                $durationMinutes = $calculated;
            }
        }

        $activeDays = null;
        if ($scheduleMode === 'weekly' && ! empty($input['active_days']) && is_array($input['active_days'])) {
            $activeDays = array_values(array_unique(array_map(
                fn ($d) => ucfirst(strtolower(trim((string) $d))),
                $input['active_days']
            )));
        } elseif ($scheduleMode === 'weekly') {
            $activeDays = self::DEFAULT_ACTIVE_DAYS;
        }

        return array_merge($input, [
            'schedule_mode' => $scheduleMode,
            'is_default_weekly' => $isDefaultWeekly,
            'effective_date' => $effectiveDate,
            'active_days' => $activeDays,
            'day' => $day,
            'day_order' => Timetable::dayOrderForName($day),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $durationMinutes,
            'teacher_id' => $this->resolveTeacherUserId($input['teacher_id'] ?? null),
            'room_number' => isset($input['room_number']) && trim((string) $input['room_number']) !== ''
                ? trim((string) $input['room_number'])
                : null,
        ]);
    }

    /**
     * Resolve teacher selection to a valid users.id (accepts users.id or teachers.id; returns null if blank/unmatched).
     */
    private function resolveTeacherUserId(mixed $rawTeacherId): ?int
    {
        if ($rawTeacherId === null || $rawTeacherId === '' || ! is_numeric($rawTeacherId)) {
            return null;
        }
        $id = (int) $rawTeacherId;
        if ($id <= 0) {
            return null;
        }
        if (\App\Models\User::where('id', $id)->exists()) {
            return $id;
        }
        $teacherUserId = \App\Models\Teacher::withTrashed()->where('id', $id)->value('user_id');

        return $teacherUserId ? (int) $teacherUserId : null;
    }

    private function normalizeTime(?string $time): ?string
    {
        if ($time === null || trim($time) === '') {
            return null;
        }
        $parts = explode(':', trim($time));
        if (count($parts) < 2) {
            return trim($time);
        }

        return sprintf('%02d:%02d', (int) $parts[0], (int) $parts[1]);
    }

    private function addMinutesToTime(string $hhmm, int $minutes): string
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));
        $total = (($h * 60 + $m + $minutes) % 1440 + 1440) % 1440;

        return sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    }

    private function detectConflict(
        int $branchId,
        string $grade,
        string $section,
        ?int $academicYearId,
        ?string $academicYearName,
        string $scheduleMode,
        ?string $effectiveDate,
        string $day,
        int $periodNumber,
        ?int $teacherId,
        array $ignoreIds
    ): ?array {
        $classConflictQuery = Timetable::where('branch_id', $branchId)
            ->where('grade', $grade)
            ->where('section', $section)
            ->where('schedule_mode', $scheduleMode)
            ->where('day', $day)
            ->where('period_number', $periodNumber);

        $this->applyAcademicYearScope($classConflictQuery, $academicYearId, $academicYearName);

        if ($scheduleMode === 'daily') {
            $classConflictQuery->whereDate('effective_date', $effectiveDate);
        }
        if (! empty($ignoreIds)) {
            $classConflictQuery->whereNotIn('id', $ignoreIds);
        }

        if ($classConflictQuery->exists()) {
            return [
                'success' => false,
                'message' => "Timetable conflict: Grade {$grade}-{$section} already has Period {$periodNumber} on {$day}".($effectiveDate ? " ({$effectiveDate})" : ''),
                'conflict_type' => 'class_slot',
            ];
        }

        // Teacher slot conflict validation intentionally disabled per product requirement
        return null;
    }
}
