<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\FeeStructure;
use App\Models\FeePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FeeController extends Controller
{
    use PaginatesAndSorts;

    // Fee Structures with server-side pagination
    public function indexStructures(Request $request)
    {
        try {
            $query = FeeStructure::with(['branch', 'creator']);

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

    public function storeStructure(Request $request)
    {
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
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $structure = FeeStructure::create([
                ...$request->all(),
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
                'is_active' => 'boolean'
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

    // Fee Payments
    public function indexPayments(Request $request)
    {
        try {
            $query = FeePayment::with(['feeStructure', 'student', 'creator']);

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

            // Apply pagination and sorting
            $payments = $this->paginateAndSort($query, $request, $sortableColumns, 'payment_date', 'desc');

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

            $totalAmount = $request->amount_paid + ($request->late_fee ?? 0) - ($request->discount_amount ?? 0);

            $payment = FeePayment::create([
                ...$request->all(),
                'total_amount' => $totalAmount,
                'created_by' => $request->user()->id
            ]);

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
                ->map(function($fee) {
                    // Ensure fee_type is never null
                    if (empty($fee->fee_type)) {
                        $fee->fee_type = 'General Fee';
                    }
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

    // Legacy methods for compatibility
    public function index() { return $this->indexStructures(request()); }
    public function store(Request $request) { return $this->storeStructure($request); }
    public function update(Request $request, string $id) { return $this->updateStructure($request, $id); }
    public function destroy(string $id) { return $this->destroyStructure($id); }
}
