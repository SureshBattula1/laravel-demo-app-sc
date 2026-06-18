<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\FeeDue;
use App\Models\Student;
use App\Services\FeeDuesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FeeDuesController extends Controller
{
    use PaginatesAndSorts;

    protected $duesService;

    public function __construct(FeeDuesService $duesService)
    {
        $this->duesService = $duesService;
    }

    /**
     * List all dues with filters grouped by fee_type
     */
    public function index(Request $request)
    {
        try {
            $query = FeeDue::with(['student.user', 'feeStructure']);

            // Branch filtering
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereHas('student', function($q) use ($accessibleBranchIds) {
                        $q->whereIn('branch_id', $accessibleBranchIds);
                    });
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('fee_type')) {
                $query->where('fee_type', $request->fee_type);
            }

            if ($request->has('grade')) {
                $query->where('current_grade', $request->grade);
            }

            if ($request->has('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'due_date');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 25);
            $dues = $query->paginate($perPage);

            // Group by fee_type for summary
            $allDues = FeeDue::whereIn('id', $dues->pluck('id'))->get();
            $grouped = $allDues->groupBy('fee_type')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'total_balance' => $group->sum('balance_amount')
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $dues->items(),
                'grouped_by_fee_type' => $grouped,
                'meta' => [
                    'current_page' => $dues->currentPage(),
                    'per_page' => $dues->perPage(),
                    'total' => $dues->total(),
                    'last_page' => $dues->lastPage()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching dues', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error fetching dues',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get dues for specific student with fee_type breakdown
     */
    public function getStudentDues($studentId)
    {
        try {
            $student = Student::where('user_id', $studentId)->orWhere('id', $studentId)->first();
            
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            $result = $this->duesService->getStudentDues($student->user_id, request()->all());

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching student dues', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error fetching student dues',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get dues grouped by fee_type for a student
     */
    public function getDuesByFeeType($studentId)
    {
        try {
            $student = Student::where('user_id', $studentId)->orWhere('id', $studentId)->first();
            
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            $result = $this->duesService->getStudentDues($student->user_id);
            
            return response()->json([
                'success' => true,
                'data' => $result['dues_by_type']
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching dues by fee type', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error fetching dues by fee type',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Apply payment to specific dues
     */
    public function applyPayment(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'payment_id' => 'required|exists:fee_payments,id',
                'due_ids' => 'required|array',
                'amounts' => 'required|array|size:' . count($request->due_ids ?? [])
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $this->duesService->applyPaymentToDues(
                $request->payment_id,
                $request->due_ids,
                $request->amounts
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment applied to dues successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error applying payment to dues', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error applying payment to dues',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Waive a due (with approval workflow)
     */
    public function waiveDue(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $due = FeeDue::findOrFail($id);
            $amountBefore = $due->balance_amount;
            
            $due->update([
                'status' => 'Waived',
                'balance_amount' => 0,
                'metadata' => array_merge($due->metadata ?? [], [
                    'waived_reason' => $request->reason,
                    'waived_by' => $request->user()->id,
                    'waived_at' => now()->toDateTimeString()
                ])
            ]);

            // Log audit trail
            try {
                $auditService = new \App\Services\AuditService();
                $auditService->log('Waiver', $due->student_id, [
                    'fee_due_id' => $due->id,
                    'amount_before' => $amountBefore,
                    'amount_after' => 0,
                    'action_amount' => $amountBefore,
                    'reason' => $request->reason,
                    'metadata' => [
                        'fee_type' => $due->fee_type,
                        'waived_by' => $request->user()->id
                    ],
                    'user_id' => $request->user()->id
                ], $request);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to log audit for waiver', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Due waived successfully',
                'data' => $due
            ]);

        } catch (\Exception $e) {
            Log::error('Error waiving due', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error waiving due',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Export dues report
     */
    public function exportReport(Request $request)
    {
        try {
            $report = $this->duesService->generateDuesReport($request->all());
            
            // TODO: Implement export to Excel/PDF/CSV
            return response()->json([
                'success' => true,
                'message' => 'Export functionality will be implemented',
                'data' => $report
            ]);

        } catch (\Exception $e) {
            Log::error('Error exporting dues report', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error exporting dues report',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get aging analysis data by fee_type
     */
    public function getAgingAnalysis(Request $request)
    {
        try {
            $result = $this->duesService->getOverdueFees($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting aging analysis', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error getting aging analysis',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get overdue fees grouped by fee_type
     */
    public function getOverdueFees(Request $request)
    {
        try {
            $result = $this->duesService->getOverdueFees($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting overdue fees', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error getting overdue fees',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}
