<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\FeeStructure;
use App\Models\FeePayment;
use App\Services\AcademicYearContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FeeController extends Controller
{
    use PaginatesAndSorts;

    public function __construct(
        protected AcademicYearContext $academicYearContext
    ) {}

    // Fee Structures with server-side pagination
    public function indexStructures(Request $request)
    {
        try {
            $query = FeeStructure::with(['branch', 'creator']);

            // 🔥 APPLY SCHOOL FILTERING - School-level isolation
            $schoolId = $this->getCurrentSchoolId($request);
            if ($schoolId) {
                $query->where('school_id', $schoolId);
            }

            // 🔥 APPLY BRANCH FILTERING - Restrict to accessible branches
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            if ($request->has('branch_id') && $accessibleBranchIds === 'all') {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('grade')) {
                $query->where('grade', $request->grade);
            }

            if ($request->has('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }

            if ($request->has('fee_type')) {
                $query->where('fee_type', $request->fee_type);
            }

            // OPTIMIZED Search filter - prefix search for better index usage
            if ($request->has('search') && !empty($request->search)) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('grade', 'like', "{$search}%")
                      ->orWhere('fee_type', 'like', "{$search}%")
                      ->orWhere('academic_year', 'like', "{$search}%");
                });
            }

            // Define sortable columns
            $sortableColumns = ['id', 'grade', 'fee_type', 'amount', 'academic_year', 'due_date', 'is_active', 'created_at'];

            // Apply pagination and sorting (default: 25 per page)
            $structures = $this->paginateAndSort($query, $request, $sortableColumns, 'grade', 'asc');

            // Map data to ensure fee_type is never empty
            $data = collect($structures->items())->map(function($fee) {
                if (empty($fee->fee_type)) {
                    $fee->fee_type = 'General Fee';
                }
                return $fee;
            });

            // Return standardized paginated response
            return response()->json([
                'success' => true,
                'message' => 'Fee structures retrieved successfully',
                'data' => $data,
                'meta' => [
                    'current_page' => $structures->currentPage(),
                    'per_page' => $structures->perPage(),
                    'total' => $structures->total(),
                    'last_page' => $structures->lastPage(),
                    'from' => $structures->firstItem(),
                    'to' => $structures->lastItem(),
                    'has_more_pages' => $structures->hasMorePages()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching fee structures: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching fee structures',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created fee structure
     * 
     * NOTE: This method accepts optional breakdown fields (tuition_fee, admission_fee, etc.)
     * These fields are stored in the database but are optional. The main 'amount' field
     * is the primary fee amount. Breakdown fields can be used for detailed fee reporting.
     */
    public function storeStructure(Request $request)
    {
        $this->academicYearContext->rejectIfPast('Creating fee structure is not allowed for past academic years.');

        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|exists:branches,id',
                'grade' => 'required|string|max:50',
                'fee_type' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0',
                'academic_year' => 'required|string|max:20',
                'due_date' => 'nullable|date',
                'description' => 'nullable|string',
                'is_recurring' => 'boolean',
                'recurrence_period' => 'nullable|string|in:Monthly,Quarterly,Annually',
                'is_active' => 'boolean',
                // Optional breakdown fields
                'tuition_fee' => 'nullable|numeric|min:0',
                'admission_fee' => 'nullable|numeric|min:0',
                'exam_fee' => 'nullable|numeric|min:0',
                'library_fee' => 'nullable|numeric|min:0',
                'transport_fee' => 'nullable|numeric|min:0',
                'sports_fee' => 'nullable|numeric|min:0',
                'lab_fee' => 'nullable|numeric|min:0',
                'other_fees' => 'nullable|array',
                'total_amount' => 'nullable|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $branch = \App\Models\Branch::find($request->branch_id);
            $structure = FeeStructure::create([
                ...$request->all(),
                'school_id' => $branch ? $branch->school_id : null,
                'created_by' => $request->user()->id,
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $structure->load(['branch', 'creator']),
                'message' => 'Fee structure created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating fee structure: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating fee structure',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStructure(Request $request, string $id)
    {
        DB::beginTransaction();
        try {
            $structure = FeeStructure::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'grade' => 'string|max:50',
                'fee_type' => 'string|max:255',
                'amount' => 'numeric|min:0',
                'academic_year' => 'string|max:20',
                'due_date' => 'nullable|date',
                'description' => 'nullable|string',
                'is_recurring' => 'boolean',
                'recurrence_period' => 'nullable|string|in:Monthly,Quarterly,Annually',
                'is_active' => 'boolean',
                // Optional breakdown fields
                'tuition_fee' => 'nullable|numeric|min:0',
                'admission_fee' => 'nullable|numeric|min:0',
                'exam_fee' => 'nullable|numeric|min:0',
                'library_fee' => 'nullable|numeric|min:0',
                'transport_fee' => 'nullable|numeric|min:0',
                'sports_fee' => 'nullable|numeric|min:0',
                'lab_fee' => 'nullable|numeric|min:0',
                'other_fees' => 'nullable|array',
                'total_amount' => 'nullable|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $structure->update([
                ...$request->all(),
                'updated_by' => $request->user()->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $structure->load(['branch', 'creator', 'updater']),
                'message' => 'Fee structure updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating fee structure: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating fee structure',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroyStructure(string $id)
    {
        DB::beginTransaction();
        try {
            $structure = FeeStructure::findOrFail($id);
            
            if ($structure->payments()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete fee structure with existing payments'
                ], 422);
            }

            $structure->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Fee structure deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting fee structure: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting fee structure',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payments dashboard overview with date range filtering
     * ✅ OPTIMIZED: Returns aggregated data for dashboard view
     * Supports: today, week, month, custom date range
     */
    public function getTodayPayments(Request $request)
    {
        try {
            // Parse date range from request (similar to DashboardController)
            $dateRange = $this->parsePaymentDateRange($request);
            $fromDate = $dateRange['from'];
            $toDate = $dateRange['to'];
            $period = $request->get('period', 'today');
            
            // Log for debugging
            Log::info('Payments Dashboard Request', [
                'period' => $period,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'filters' => $request->all()
            ]);
            
            // Base query for payments (including Completed and Partial payments)
            // Get branch_id from fee_structure (more reliable than student record)
            $baseQuery = DB::table('fee_payments as fp')
                ->join('users as u', 'fp.student_id', '=', 'u.id')
                ->leftJoin('students as s', 'u.id', '=', 's.user_id')
                ->leftJoin('fee_structures as fs', 'fp.fee_structure_id', '=', 'fs.id')
                ->leftJoin('branches as b', 'fs.branch_id', '=', 'b.id') // Get branch from fee_structure
                ->leftJoin('grades as g', 's.grade', '=', 'g.value')
                ->whereBetween('fp.payment_date', [$fromDate, $toDate])
                ->whereIn('fp.payment_status', ['Completed', 'Partial']);

            // 🔥 APPLY SCHOOL FILTERING - Only current school's data
            $schoolId = $this->getCurrentSchoolId($request);
            if ($schoolId) {
                $baseQuery->where(function ($q) use ($schoolId) {
                    $q->where('fp.school_id', $schoolId)
                      ->orWhere('fs.school_id', $schoolId);
                });
            }

            // 🔥 APPLY BRANCH FILTERING - Restrict to accessible branches
            // Use branch_id from fee_structure (more reliable)
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $baseQuery->whereIn('fs.branch_id', $accessibleBranchIds);
                } else {
                    $baseQuery->whereRaw('1 = 0');
                }
            }

            // Filter by branch
            if ($request->has('branch_id') && $request->branch_id) {
                $baseQuery->where('fs.branch_id', $request->branch_id);
            }

            // Filter by grade/class
            if ($request->has('grade') && $request->grade) {
                $baseQuery->where('s.grade', $request->grade);
            }

            // Filter by section
            if ($request->has('section') && $request->section) {
                $baseQuery->where('s.section', $request->section);
            }

            // Filter by payment method
            if ($request->has('payment_method') && $request->payment_method) {
                $baseQuery->where('fp.payment_method', $request->payment_method);
            }

            // ✅ Get Total Amount Paid (from both Completed and Partial payments)
            $totalAmount = (float) (clone $baseQuery)->sum('fp.amount_paid');
            $totalDiscount = (float) (clone $baseQuery)->sum('fp.discount_amount');
            $totalLateFee = (float) (clone $baseQuery)->sum('fp.late_fee');

            // ✅ Get Total Count (including both Completed and Partial payments)
            $totalCount = (clone $baseQuery)->count('fp.id');
            
            // ✅ Get breakdown by payment status
            $byStatus = (clone $baseQuery)
                ->select(
                    'fp.payment_status',
                    DB::raw('COUNT(DISTINCT fp.id) as payment_count'),
                    DB::raw('SUM(fp.amount_paid) as total_amount')
                )
                ->groupBy('fp.payment_status')
                ->orderBy('fp.payment_status')
                ->get()
                ->map(function($item) {
                    return [
                        'payment_status' => $item->payment_status,
                        'payment_count' => (int) $item->payment_count,
                        'total_amount' => (float) $item->total_amount
                    ];
                });
            
            // Log for debugging
            Log::info('Payments Summary', [
                'total_amount' => $totalAmount,
                'total_count' => $totalCount,
                'period' => $period,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'includes_partial' => true
            ]);

            // ✅ Get Class/Grade Wise Breakdown (with pending payments)
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            
            // Get paid amounts by grade from payments (including discount and late fee)
            $paidByGrade = (clone $baseQuery)
                ->select(
                    's.grade',
                    DB::raw('COALESCE(g.label, CONCAT("Grade ", s.grade)) as grade_label'),
                    DB::raw('COUNT(DISTINCT fp.id) as payment_count'),
                    DB::raw('SUM(fp.amount_paid) as total_amount'),
                    DB::raw('SUM(COALESCE(fp.discount_amount, 0)) as total_discount'),
                    DB::raw('SUM(COALESCE(fp.late_fee, 0)) as total_late_fee'),
                    DB::raw('COUNT(DISTINCT s.id) as student_count')
                )
                ->groupBy('s.grade', 'g.label')
                ->get()
                ->keyBy('grade');
            
            // Calculate pending amounts by grade
            // Use subquery to aggregate payments per (fee_structure_id, student_id) to avoid
            // double-counting expected_amount when students have multiple (partial) payments
            // Pending = (fee_amount - total_discount) - amount_paid (late fee not counted)
            $paidAggregateSubquery = DB::table('fee_payments')
                ->whereIn('payment_status', ['Completed', 'Partial'])
                ->select(
                    'fee_structure_id',
                    'student_id',
                    DB::raw('SUM(COALESCE(amount_paid, 0)) as amount_paid'),
                    DB::raw('SUM(COALESCE(discount_amount, 0)) as total_discount')
                )
                ->groupBy('fee_structure_id', 'student_id');

            $pendingQuery = DB::table('fee_structures as fs')
                ->join('students as s', function($join) {
                    $join->where('s.student_status', '=', 'Active')
                        ->where(function($q) {
                            $q->whereColumn('fs.grade', 's.grade')
                              ->orWhereRaw('fs.grade = CONCAT("Grade ", s.grade)')
                              ->orWhereRaw('s.grade = CONCAT("Grade ", TRIM(fs.grade))')
                              ->orWhereRaw('TRIM(REPLACE(s.grade, "Grade ", "")) = TRIM(fs.grade)')
                              ->orWhereRaw('TRIM(REPLACE(fs.grade, "Grade ", "")) = TRIM(s.grade)');
                        });
                })
                ->where(function($q) use ($request) {
                    if ($request->has('academic_year') && $request->academic_year) {
                        $ay = $request->academic_year;
                        $q->where('fs.academic_year', $ay)
                          ->where(function($q2) use ($ay) {
                              $q2->where('s.academic_year', $ay)
                                 ->orWhereNull('s.academic_year')
                                 ->orWhere('s.academic_year', '');
                          });
                    } else {
                        $q->whereColumn('fs.academic_year', 's.academic_year');
                    }
                })
                ->whereColumn('fs.branch_id', 's.branch_id')
                ->leftJoinSub($paidAggregateSubquery, 'paid', function($join) {
                    $join->on('fs.id', '=', 'paid.fee_structure_id')
                         ->on('s.user_id', '=', 'paid.student_id');
                })
                ->leftJoin('grades as g', 's.grade', '=', 'g.value')
                ->where('fs.is_active', true)
                ->select(
                    's.grade',
                    DB::raw('COALESCE(g.label, CONCAT("Grade ", s.grade)) as grade_label'),
                    DB::raw('COUNT(DISTINCT s.id) as total_students'),
                    DB::raw('SUM(fs.amount) as expected_amount'),
                    DB::raw('SUM(COALESCE(paid.amount_paid, 0)) as paid_amount'),
                    DB::raw('SUM(COALESCE(paid.total_discount, 0)) as total_discount')
                )
                ->groupBy('s.grade', 'g.label');

            // Apply school filtering to pending query
            if ($schoolId) {
                $pendingQuery->where(function ($q) use ($schoolId) {
                    $q->where('fs.school_id', $schoolId)
                      ->orWhere('s.school_id', $schoolId);
                });
            }
            
            // Apply branch filtering
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $pendingQuery->whereIn('fs.branch_id', $accessibleBranchIds)
                                 ->whereIn('s.branch_id', $accessibleBranchIds);
                } else {
                    $pendingQuery->whereRaw('1 = 0');
                }
            }
            
            // Filter by branch if specified; also use branch's current_academic_year when no academic_year in request
            if ($request->has('branch_id') && $request->branch_id) {
                $pendingQuery->where('fs.branch_id', $request->branch_id)
                             ->where('s.branch_id', $request->branch_id);
                if (!$request->has('academic_year') || !$request->academic_year) {
                    $branch = \App\Models\Branch::find($request->branch_id);
                    if ($branch && $branch->current_academic_year) {
                        $pendingQuery->where('fs.academic_year', $branch->current_academic_year)
                                     ->where(function($q) use ($branch) {
                                         $q->where('s.academic_year', $branch->current_academic_year)
                                           ->orWhereNull('s.academic_year')
                                           ->orWhere('s.academic_year', '');
                                     });
                    }
                }
            }
            
            $pendingByGrade = $pendingQuery->get()->keyBy('grade');
            
            // Merge paid and pending data
            $allGrades = $paidByGrade->keys()->merge($pendingByGrade->keys())->unique();
            
            $byGrade = $allGrades->map(function($grade) use ($paidByGrade, $pendingByGrade) {
                $paid = $paidByGrade->get($grade);
                $pending = $pendingByGrade->get($grade);
                
                $expectedAmount = $pending ? (float) $pending->expected_amount : 0;
                $totalDiscountPending = $pending ? (float) ($pending->total_discount ?? 0) : 0;
                $paidAmount = $pending ? (float) $pending->paid_amount : 0;
                $expectedAfterDiscount = max(0, $expectedAmount - $totalDiscountPending);
                $pendingAmount = max(0, $expectedAfterDiscount - $paidAmount);
                
                return [
                    'grade' => $grade,
                    'grade_label' => $paid ? $paid->grade_label : ($pending ? $pending->grade_label : "Grade $grade"),
                    'payment_count' => $paid ? (int) $paid->payment_count : 0,
                    'total_amount' => $paid ? (float) $paid->total_amount : 0,
                    'total_discount' => $paid ? (float) ($paid->total_discount ?? 0) : 0,
                    'total_late_fee' => $paid ? (float) ($paid->total_late_fee ?? 0) : 0,
                    'student_count' => $paid ? (int) $paid->student_count : ($pending ? (int) $pending->total_students : 0),
                    'pending_amount' => round($pendingAmount, 2),
                    'pending_count' => $pendingAmount > 0 ? 1 : 0
                ];
            })->sortBy('grade')->values();

            // ✅ Get Section Wise Breakdown
            $bySection = (clone $baseQuery)
                ->select(
                    's.grade',
                    DB::raw('COALESCE(g.label, CONCAT("Grade ", s.grade)) as grade_label'),
                    's.section',
                    DB::raw('COUNT(DISTINCT fp.id) as payment_count'),
                    DB::raw('SUM(fp.amount_paid) as total_amount'),
                    DB::raw('COUNT(DISTINCT fp.student_id) as student_count')
                )
                ->whereNotNull('s.section')
                ->whereNotNull('s.grade')
                ->groupBy('s.grade', 'g.label', 's.section')
                ->orderBy('s.grade')
                ->orderBy('s.section')
                ->get()
                ->map(function($item) {
                    return [
                        'grade' => $item->grade,
                        'grade_label' => $item->grade_label,
                        'section' => $item->section,
                        'payment_count' => (int) $item->payment_count,
                        'total_amount' => (float) $item->total_amount,
                        'student_count' => (int) $item->student_count
                    ];
                });

            // ✅ Get Branch Wise Breakdown
            // Use branch_id from fee_structure
            $byBranch = (clone $baseQuery)
                ->select(
                    'fs.branch_id',
                    'b.name as branch_name',
                    'b.code as branch_code',
                    DB::raw('COUNT(DISTINCT fp.id) as payment_count'),
                    DB::raw('SUM(fp.amount_paid) as total_amount'),
                    DB::raw('COUNT(DISTINCT fp.student_id) as student_count')
                )
                ->whereNotNull('fs.branch_id')
                ->groupBy('fs.branch_id', 'b.name', 'b.code')
                ->orderBy('b.name')
                ->get()
                ->map(function($item) {
                    return [
                        'branch_id' => $item->branch_id,
                        'branch_name' => $item->branch_name,
                        'branch_code' => $item->branch_code,
                        'payment_count' => (int) $item->payment_count,
                        'total_amount' => (float) $item->total_amount,
                        'student_count' => (int) $item->student_count
                    ];
                });

            // ✅ Get Payment Method Breakdown
            $byPaymentMethod = (clone $baseQuery)
                ->select(
                    'fp.payment_method',
                    DB::raw('COUNT(DISTINCT fp.id) as payment_count'),
                    DB::raw('SUM(fp.amount_paid) as total_amount')
                )
                ->groupBy('fp.payment_method')
                ->orderBy('fp.payment_method')
                ->get()
                ->map(function($item) {
                    return [
                        'payment_method' => $item->payment_method,
                        'payment_count' => (int) $item->payment_count,
                        'total_amount' => (float) $item->total_amount
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Payments dashboard retrieved successfully (includes Completed and Partial payments)',
                'data' => [
                    'summary' => [
                        'total_amount' => round($totalAmount, 2),
                        'total_discount' => round($totalDiscount, 2),
                        'total_late_fee' => round($totalLateFee, 2),
                        'total_count' => $totalCount,
                        'period' => $period,
                        'from_date' => $fromDate,
                        'to_date' => $toDate,
                        'date' => $fromDate === $toDate ? $fromDate : $fromDate . ' to ' . $toDate,
                        'includes_partial_payments' => true
                    ],
                    'by_status' => $byStatus,
                    'by_grade' => $byGrade,
                    'by_section' => $bySection,
                    'by_branch' => $byBranch,
                    'by_payment_method' => $byPaymentMethod
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching today\'s payments dashboard: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching today\'s payments dashboard',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    // Fee Payments
    public function indexPayments(Request $request)
    {
        try {
            $query = FeePayment::with(['feeStructure', 'student', 'creator']);

            // 🔥 APPLY SCHOOL FILTERING - School-level isolation
            $schoolId = $this->getCurrentSchoolId($request);
            if ($schoolId) {
                $query->where('school_id', $schoolId);
            }

            // 🔥 APPLY BRANCH FILTERING - Restrict to accessible branches
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            if ($request->has('student_id')) {
                $query->where('student_id', $request->student_id);
            }

            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            if ($request->has('payment_method')) {
                $query->where('payment_method', $request->payment_method);
            }

            if ($request->has('from_date')) {
                $query->whereDate('payment_date', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate('payment_date', '<=', $request->to_date);
            }

            // OPTIMIZED Search filter - prefix search for better index usage
            if ($request->has('search') && !empty($request->search)) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('transaction_id', 'like', "{$search}%")
                      ->orWhere('payment_method', 'like', "{$search}%");
                });
            }

            // Define sortable columns
            $sortableColumns = ['id', 'student_id', 'payment_date', 'amount_paid', 'payment_method', 'payment_status', 'created_at'];

            // Apply pagination and sorting (default: created_at desc)
            $payments = $this->paginateAndSort($query, $request, $sortableColumns, 'created_at', 'desc');

            // Map data to ensure fee_type is never empty in feeStructure relationship
            $data = collect($payments->items())->map(function($payment) {
                if ($payment->feeStructure && empty($payment->feeStructure->fee_type)) {
                    $payment->feeStructure->fee_type = 'General Fee';
                }
                return $payment;
            });

            return response()->json([
                'success' => true,
                'message' => 'Fee payments retrieved successfully',
                'data' => $data,
                'meta' => [
                    'current_page' => $payments->currentPage(),
                    'per_page' => $payments->perPage(),
                    'total' => $payments->total(),
                    'last_page' => $payments->lastPage(),
                    'from' => $payments->firstItem(),
                    'to' => $payments->lastItem(),
                    'has_more_pages' => $payments->hasMorePages()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching fee payments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching fee payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function recordPayment(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'fee_structure_id' => 'required|exists:fee_structures,id',
                'student_id' => 'required|exists:users,id',
                'amount_paid' => 'required|numeric|min:0',
                'payment_date' => 'required|date',
                'payment_method' => 'required|string|in:Cash,Card,Online,Cheque,Other',
                'transaction_id' => 'nullable|string',
                'discount_amount' => 'nullable|numeric|min:0',
                'late_fee' => 'nullable|numeric|min:0',
                'payment_status' => 'required|string|in:Pending,Partial,Completed,Failed,Refunded',
                'remarks' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $feeStructure = FeeStructure::find($request->fee_structure_id);
            $lateFee = (float) ($request->late_fee ?? 0);
            $amountPaid = (float) $request->amount_paid;

            // Overpayment validation: Amount Paid must not exceed Remaining + Late Fee (already paid excludes late fee)
            $previousPayments = FeePayment::where('student_id', $request->student_id)
                ->where('fee_structure_id', $request->fee_structure_id)
                ->whereIn('payment_status', ['Completed', 'Partial'])
                ->get();
            $alreadyPaid = (float) $previousPayments->sum('amount_paid');
            $totalDiscount = (float) $previousPayments->sum('discount_amount') + (float) ($request->discount_amount ?? 0);
            $remainingBeforePayment = max(0, (float) $feeStructure->amount - $alreadyPaid - $totalDiscount);
            $maxAllowed = $remainingBeforePayment + $lateFee;

            if ($amountPaid > $maxAllowed) {
                return response()->json([
                    'success' => false,
                    'message' => 'You entered more than the actual amount. Maximum allowed: ₹' . number_format($maxAllowed, 2) . ' (Remaining: ₹' . number_format($remainingBeforePayment, 2) . ' + Late Fee: ₹' . number_format($lateFee, 2) . ')',
                ], 422);
            }

            // Total collected in this payment (discount applies to fee amount, not this payment)
            $totalAmount = $amountPaid + $lateFee;
            $branchId = $feeStructure ? (int) $feeStructure->branch_id : null;
            $schoolId = $feeStructure?->school_id
                ?? ($branchId ? \App\Models\Branch::find($branchId)?->school_id : null)
                ?? $this->getCurrentSchoolId($request);
            if ($schoolId !== null) {
                $schoolId = (int) $schoolId;
            }

            $paymentData = [
                'fee_structure_id' => $request->fee_structure_id,
                'student_id' => $request->student_id,
                'branch_id' => $branchId,
                'school_id' => $schoolId,
                'amount_paid' => $request->amount_paid,
                'payment_date' => $request->payment_date,
                'payment_method' => $request->payment_method,
                'transaction_id' => $request->transaction_id,
                'discount_amount' => $request->discount_amount ?? 0,
                'late_fee' => $request->late_fee ?? 0,
                'total_amount' => $totalAmount,
                'payment_status' => $request->payment_status,
                'remarks' => $request->remarks,
                'academic_year' => $request->academic_year,
                'created_by' => $request->user()->id
            ];

            $payment = FeePayment::create($paymentData);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $payment->load(['feeStructure', 'student', 'creator']),
                'message' => 'Payment recorded successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error recording payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error recording payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getStudentFees(string $studentId)
    {
        try {
            // Get student details to filter fee structures by branch and grade
            // studentId here is actually the user_id
            $student = \App\Models\Student::where('user_id', $studentId)->first();
            
            // If no student record exists, return empty data instead of error
            if (!$student) {
                Log::warning('No student record found for user_id', ['user_id' => $studentId]);
                return response()->json([
                    'success' => true,
                    'data' => [
                        'payments' => [],
                        'pending_fees' => [],
                        'total_paid' => 0,
                        'pending_count' => 0
                    ],
                    'message' => 'No student record found for this user'
                ]);
            }
            
            // Log student details for debugging
            Log::info('Getting fees for student', [
                'user_id' => $studentId,
                'student_id' => $student->id,
                'branch_id' => $student->branch_id,
                'grade' => $student->grade
            ]);
            
            // OPTIMIZED: Use SQL aggregation - Count completed AND partial payments
            $totalPaid = FeePayment::where('student_id', $studentId)
                ->whereIn('payment_status', ['Completed', 'Partial'])
                ->sum('total_amount');

            // OPTIMIZED: Get FULLY paid structure IDs - Only from completed payments (not partial)
            // Partial payments keep the fee in pending list
            $paidStructureIds = FeePayment::where('student_id', $studentId)
                ->where('payment_status', 'Completed')  // Only "Completed" removes from pending
                ->pluck('fee_structure_id')
                ->filter()
                ->unique()
                ->toArray();

            // Check all fee structures for this branch (without grade filter first)
            $allBranchFees = FeeStructure::where('is_active', true)
                ->where('branch_id', $student->branch_id)
                ->get();
            
            Log::info('Fee structures debugging', [
                'student_user_id' => $studentId,
                'student_branch_id' => $student->branch_id,
                'student_grade' => $student->grade,
                'student_grade_type' => gettype($student->grade),
                'total_branch_fees' => $allBranchFees->count(),
                'grades_in_fee_structures' => $allBranchFees->pluck('grade')->unique()->toArray()
            ]);

            // OPTIMIZED: Get pending fees - Only for student's branch and grade
            // Use flexible grade matching (handles "1", "Grade 1", "I", etc.)
            $pending = FeeStructure::where('is_active', true)
                ->where('branch_id', $student->branch_id)
                ->where(function($q) use ($student) {
                    // Exact match
                    $q->where('grade', $student->grade)
                      // Try with "Grade " prefix
                      ->orWhere('grade', 'Grade ' . $student->grade)
                      // Try without "Grade " prefix if student has it
                      ->orWhere('grade', str_replace('Grade ', '', $student->grade));
                })
                ->when(!empty($paidStructureIds), function($q) use ($paidStructureIds) {
                    return $q->whereNotIn('id', $paidStructureIds);
                })
                ->get()
                ->map(function($fee) use ($studentId) {
                    // Ensure fee_type is never null
                    if (empty($fee->fee_type)) {
                        $fee->fee_type = 'General Fee';
                    }
                    
                    // Calculate amount already paid (excludes late fee - late fee is extra charge)
                    $amountPaid = FeePayment::where('student_id', $studentId)
                        ->where('fee_structure_id', $fee->id)
                        ->whereIn('payment_status', ['Completed', 'Partial'])
                        ->sum('amount_paid');
                    
                    // Add amount_paid and remaining_amount to fee structure
                    $fee->amount_paid = (float) $amountPaid;
                    $fee->remaining_amount = max(0, (float) $fee->amount - (float) $amountPaid);
                    
                    return $fee;
                });

            // OPTIMIZED: Get payments with pagination (limit to recent 50 payments)
            $payments = FeePayment::with(['feeStructure', 'creator'])
                ->where('student_id', $studentId)
                ->orderBy('payment_date', 'desc')
                ->limit(50)
                ->get()
                ->map(function($payment) {
                    // Ensure fee_type is never null in fee_structure relationship
                    if ($payment->feeStructure && empty($payment->feeStructure->fee_type)) {
                        $payment->feeStructure->fee_type = 'General Fee';
                    }
                    return $payment;
                });

            Log::info('Fee data summary', [
                'pending_fees_count' => $pending->count(),
                'payments_count' => $payments->count(),
                'total_paid' => $totalPaid,
                'paid_structure_ids' => $paidStructureIds,
                'pending_fee_types' => $pending->pluck('fee_type')->toArray()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'payments' => $payments,
                    'pending_fees' => $pending,
                    'total_paid' => (float)$totalPaid,
                    'pending_count' => $pending->count()
                ],
                'message' => 'Student fees retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching student fees: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching student fees',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Show single fee structure
    public function show(string $id)
    {
        try {
            $structure = FeeStructure::with(['branch', 'creator', 'updater'])->findOrFail($id);
            
            // Ensure fee_type is never empty
            if (empty($structure->fee_type)) {
                $structure->fee_type = 'General Fee';
            }
            
            return response()->json([
                'success' => true,
                'data' => $structure
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching fee structure: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fee structure not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
    
    // Show single fee payment
    public function showPayment(string $id)
    {
        try {
            $payment = FeePayment::with(['feeStructure', 'student', 'creator'])->findOrFail($id);
            
            // Ensure fee_type is never empty in feeStructure relationship
            if ($payment->feeStructure && empty($payment->feeStructure->fee_type)) {
                $payment->feeStructure->fee_type = 'General Fee';
            }

            // Load all past transactions for this student + fee structure
            $pastTransactions = FeePayment::where('student_id', $payment->student_id)
                ->where('fee_structure_id', $payment->fee_structure_id)
                ->orderBy('payment_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get(['id', 'receipt_number', 'payment_date', 'payment_method', 'amount_paid', 'discount_amount', 'late_fee', 'total_amount', 'payment_status'])
                ->toArray();

            $payment->past_transactions = $pastTransactions;
            
            return response()->json([
                'success' => true,
                'data' => $payment
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching fee payment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Fee payment not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Download a single fee payment receipt as PDF.
     */
    public function downloadReceipt(string $id)
    {
        try {
            $payment = FeePayment::with(['feeStructure', 'student', 'creator'])->findOrFail($id);

            if ($payment->feeStructure && empty($payment->feeStructure->fee_type)) {
                $payment->feeStructure->fee_type = 'General Fee';
            }

            $studentName = $payment->student
                ? trim(($payment->student->first_name ?? '') . ' ' . ($payment->student->last_name ?? ''))
                : 'Student';

            $receiptNumber = $payment->receipt_number ?? ('PAYMENT-' . $payment->id);

            // Load all payments for this student & fee structure to show history
            $paymentHistory = FeePayment::where('student_id', $payment->student_id)
                ->where('fee_structure_id', $payment->fee_structure_id)
                ->orderBy('payment_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();

            $previousPayments = $paymentHistory->where('id', '!=', $payment->id);
            $alreadyPaid = (float) $paymentHistory->sum('amount_paid');
            $alreadyPaidExcludingCurrent = (float) $previousPayments->sum('amount_paid');
            $totalLateFee = (float) $paymentHistory->sum('late_fee');

            $html = view('pdf.fee_receipt', [
                'payment' => $payment,
                'studentName' => $studentName,
                'receiptNumber' => $receiptNumber,
                'previousPayments' => $previousPayments,
                'paymentHistory' => $paymentHistory,
                'alreadyPaid' => $alreadyPaid,
                'alreadyPaidExcludingCurrent' => $alreadyPaidExcludingCurrent,
                'totalLateFee' => $totalLateFee,
            ])->render();

            $pdf = app('dompdf.wrapper');
            $pdf->loadHTML($html);
            $pdf->setPaper('a4', 'portrait');

            $filename = sprintf('fee-receipt-%s.pdf', str_replace([' ', '#'], ['-', ''], $receiptNumber));

            return $pdf->download($filename);
        } catch (\Exception $e) {
            Log::error('Error generating fee payment receipt PDF: ' . $e->getMessage(), [
                'payment_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to generate receipt PDF',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Parse date range from request parameters (similar to DashboardController)
     */
    private function parsePaymentDateRange(Request $request): array
    {
        $period = $request->get('period', 'today');
        
        switch ($period) {
            case 'today':
                return [
                    'from' => \Carbon\Carbon::today()->toDateString(),
                    'to' => \Carbon\Carbon::today()->toDateString()
                ];
            
            case 'week':
                return [
                    'from' => \Carbon\Carbon::now()->startOfWeek()->toDateString(),
                    'to' => \Carbon\Carbon::now()->endOfWeek()->toDateString()
                ];
            
            case 'month':
                return [
                    'from' => \Carbon\Carbon::now()->startOfMonth()->toDateString(),
                    'to' => \Carbon\Carbon::now()->endOfMonth()->toDateString()
                ];
            
            case 'custom':
                return [
                    'from' => $request->get('from_date', \Carbon\Carbon::today()->toDateString()),
                    'to' => $request->get('to_date', \Carbon\Carbon::today()->toDateString())
                ];
            
            default:
                return [
                    'from' => \Carbon\Carbon::today()->toDateString(),
                    'to' => \Carbon\Carbon::today()->toDateString()
                ];
        }
    }

    // Legacy methods for compatibility
    public function index() { return $this->indexStructures(request()); }
    public function store(Request $request) { return $this->storeStructure($request); }
    public function update(Request $request, string $id) { return $this->updateStructure($request, $id); }
    public function destroy(string $id) { return $this->destroyStructure($id); }
}
