<?php

namespace App\Http\Controllers;

use App\Exports\GlobalSearchExport;
use App\Http\Traits\PaginatesAndSorts;
use App\Services\CsvExportService;
use App\Services\ExportService;
use App\Services\PdfExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Global "people" search across students and teachers/accountants/staff.
 *
 * Results are always scoped to the caller's accessible branches (via
 * getAccessibleBranchIds() / getCurrentSchoolId(), the same tenant logic the
 * per-module list endpoints use). Gated by the `search.global` permission.
 *
 * Optional filters: `branch_id` and `category` (student | teacher | accountant | staff).
 */
class GlobalSearchController extends Controller
{
    use PaginatesAndSorts;

    /** category -> teachers.category_type */
    private const CATEGORY_TYPE_MAP = [
        'teacher' => 'Teaching',
        'accountant' => 'Account',
        'staff' => 'Staff',
    ];

    /**
     * GET /global-search?q=&page=&per_page=&branch_id=&category=
     */
    public function index(Request $request)
    {
        try {
            $query = $this->resultsQuery($request);

            if (!$query) {
                return response()->json($this->emptyEnvelope($request));
            }

            $perPage = max(1, min(100, (int) $request->get('per_page', 25)));
            $paginator = $query->paginate($perPage);

            $data = collect($paginator->items())->map(fn ($row) => $this->shapeRow($row))->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Search results retrieved successfully',
                'data' => $data,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'has_more_pages' => $paginator->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Global search error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to perform search',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * GET /global-search/export?format=excel|pdf|csv (+ same filters as index)
     */
    public function export(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'format' => 'required|in:excel,pdf,csv',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $query = $this->resultsQuery($request);
            $rows = $query ? collect($query->get()) : collect();

            // Flatten to the export column shape (branch_name, name, category, phone, email).
            $exportData = $rows->map(function ($row) {
                return (object) [
                    'branch_name' => $row->branch_name,
                    'name' => $row->name,
                    'category' => ucfirst((string) $row->category),
                    'phone' => $row->phone,
                    'email' => $row->email,
                ];
            });

            $format = $request->format;

            return match ($format) {
                'excel' => Excel::download(
                    new GlobalSearchExport($exportData),
                    (new ExportService('global_search'))->generateFilename('xlsx')
                ),
                'pdf' => (function () use ($exportData) {
                    $pdf = (new PdfExportService('global_search'))
                        ->setOrientation('landscape')
                        ->generate($exportData, 'Search Results');
                    return $pdf->download((new ExportService('global_search'))->generateFilename('pdf'));
                })(),
                'csv' => (new CsvExportService('global_search'))
                    ->generate($exportData, (new ExportService('global_search'))->generateFilename('csv')),
            };
        } catch (\Exception $e) {
            Log::error('Global search export error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export search results',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Build the UNION query for the current request + filters, or null when the
     * search term is too short / no category matches.
     */
    private function resultsQuery(Request $request): ?\Illuminate\Database\Query\Builder
    {
        $term = strip_tags((string) ($request->get('q', $request->get('search', ''))));
        $term = preg_replace('/[^\w\s@.-]/', '', trim($term));

        if (mb_strlen($term) < 2) {
            return null;
        }

        $schoolId = $this->getCurrentSchoolId($request);
        $accessibleBranchIds = $this->getAccessibleBranchIds($request);
        $user = $request->user();
        $like = "{$term}%";

        // NOTE: the branch filter param is `filter_branch_id`, NOT `branch_id`, on purpose.
        // CheckPermission middleware reads `branch_id` and would run a branch-SCOPED permission
        // check (which search.global is not). `filter_branch_id` still ends in `_id` so it is
        // hashid-decoded, but is invisible to that middleware.
        $branchRaw = $request->input('filter_branch_id');
        $branchId = (is_numeric($branchRaw) && (int) $branchRaw > 0) ? (int) $branchRaw : null;

        $category = $request->filled('category') ? strtolower((string) $request->category) : null;
        $includeStudents = $category === null || $category === 'student';
        $includeTeachers = $category === null || in_array($category, ['teacher', 'accountant', 'staff'], true);

        $subqueries = [];

        if ($includeStudents) {
            $students = DB::table('students')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('branches', 'students.branch_id', '=', 'branches.id')
                ->whereNull('students.deleted_at')
                ->select([
                    DB::raw("'student' as result_type"),
                    DB::raw("'student' as category"),
                    'students.id as id',
                    DB::raw("CONCAT(users.first_name, ' ', users.last_name) as name"),
                    'users.email as email',
                    'users.phone as phone',
                    'branches.id as branch_id',
                    'branches.name as branch_name',
                    'branches.code as branch_code',
                ]);

            if ($schoolId) {
                $students->where(function ($q) use ($schoolId) {
                    $q->where('students.school_id', $schoolId)
                        ->orWhere(function ($q2) use ($schoolId) {
                            $q2->whereNull('students.school_id')
                                ->whereIn('students.branch_id', function ($sq) use ($schoolId) {
                                    $sq->select('id')->from('branches')
                                        ->where('school_id', (int) $schoolId)
                                        ->whereNull('deleted_at');
                                });
                        });
                });
            }

            if ($user && $user->role === 'Student') {
                $students->where('students.user_id', $user->id);
            }

            $this->applyBranchScope($students, 'students.branch_id', $accessibleBranchIds);

            if ($branchId) {
                $students->where('students.branch_id', $branchId);
            }

            $students->where(function ($q) use ($like) {
                $q->where('users.first_name', 'like', $like)
                    ->orWhere('users.last_name', 'like', $like)
                    ->orWhere('users.email', 'like', $like)
                    ->orWhere('students.admission_number', 'like', $like)
                    ->orWhere('students.roll_number', 'like', $like)
                    ->orWhereRaw("CONCAT(users.first_name, ' ', users.last_name) LIKE ?", [$like]);
            });

            $subqueries[] = $students;
        }

        if ($includeTeachers) {
            $teachers = DB::table('teachers')
                ->join('users', 'teachers.user_id', '=', 'users.id')
                ->leftJoin('branches', 'teachers.branch_id', '=', 'branches.id')
                ->whereNull('teachers.deleted_at')
                ->select([
                    DB::raw("'teacher' as result_type"),
                    DB::raw("CASE teachers.category_type
                        WHEN 'Account' THEN 'accountant'
                        WHEN 'Staff' THEN 'staff'
                        ELSE 'teacher' END as category"),
                    'teachers.id as id',
                    DB::raw("CONCAT(users.first_name, ' ', users.last_name) as name"),
                    'users.email as email',
                    'users.phone as phone',
                    'branches.id as branch_id',
                    'branches.name as branch_name',
                    'branches.code as branch_code',
                ]);

            if ($schoolId) {
                $teachers->where('teachers.school_id', $schoolId);
            }

            if ($user && $user->role === 'Teacher') {
                $teachers->where('teachers.user_id', $user->id);
            }

            $this->applyBranchScope($teachers, 'teachers.branch_id', $accessibleBranchIds);

            if ($branchId) {
                $teachers->where('teachers.branch_id', $branchId);
            }

            // Narrow to a specific teacher category when requested.
            if ($category !== null && isset(self::CATEGORY_TYPE_MAP[$category])) {
                $teachers->where('teachers.category_type', self::CATEGORY_TYPE_MAP[$category]);
            }

            $teachers->where(function ($q) use ($like) {
                $q->where('users.first_name', 'like', $like)
                    ->orWhere('users.last_name', 'like', $like)
                    ->orWhere('users.email', 'like', $like)
                    ->orWhere('users.phone', 'like', $like)
                    ->orWhere('teachers.employee_id', 'like', $like)
                    ->orWhere('teachers.designation', 'like', $like);
            });

            $subqueries[] = $teachers;
        }

        if (empty($subqueries)) {
            return null;
        }

        $union = array_shift($subqueries);
        foreach ($subqueries as $sub) {
            $union = $union->unionAll($sub);
        }

        return DB::query()->fromSub($union, 'r')->orderBy('name');
    }

    /**
     * Reshape a raw union row into the API response item.
     */
    private function shapeRow($row): array
    {
        return [
            'id' => $row->id,
            'type' => $row->result_type,   // 'student' | 'teacher' — drives the profile link
            'category' => $row->category,  // student | teacher | accountant | staff
            'name' => $row->name,
            'email' => $row->email,
            'phone' => $row->phone,
            'branch' => [
                'id' => $row->branch_id,
                'name' => $row->branch_name,
                'code' => $row->branch_code,
            ],
        ];
    }

    /**
     * Restrict a query to the accessible branch IDs ('all' = no restriction,
     * empty = no access -> empty result). Mirrors the list controllers.
     */
    private function applyBranchScope($query, string $branchColumn, array|string $accessibleBranchIds): void
    {
        if ($accessibleBranchIds === 'all') {
            return;
        }

        if (!empty($accessibleBranchIds)) {
            $query->whereIn($branchColumn, $accessibleBranchIds);
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * Standard empty paginated envelope (used when the term is too short).
     */
    private function emptyEnvelope(Request $request): array
    {
        $perPage = max(1, min(100, (int) $request->get('per_page', 25)));

        return [
            'success' => true,
            'message' => 'Search results retrieved successfully',
            'data' => [],
            'meta' => [
                'current_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'last_page' => 1,
                'from' => null,
                'to' => null,
                'has_more_pages' => false,
            ],
        ];
    }
}
