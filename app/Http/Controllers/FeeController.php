<?php

namespace App\Http\Controllers;

use App\Models\FeeStructure;
use App\Models\FeePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FeeController extends Controller
{
    // Fee Structures
    public function indexStructures(Request $request)
    {
        try {
            $query = FeeStructure::with(['branch', 'creator']);

            if ($request->has('branch_id')) {
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

            $structures = $query->orderBy('grade')->orderBy('fee_type')->get();

            return response()->json([
                'success' => true,
                'data' => $structures,
                'message' => 'Fee structures retrieved successfully'
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
                'fee_type' => 'required|string|in:Tuition,Library,Laboratory,Sports,Transport,Exam,Other',
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
                'fee_type' => 'string|in:Tuition,Library,Laboratory,Sports,Transport,Exam,Other',
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

            $payments = $query->orderBy('payment_date', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $payments,
                'message' => 'Fee payments retrieved successfully'
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
                'payment_status' => 'required|string|in:Pending,Completed,Failed,Refunded',
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
            $payments = FeePayment::with(['feeStructure', 'creator'])
                ->where('student_id', $studentId)
                ->orderBy('payment_date', 'desc')
                ->get();

            $totalPaid = $payments->sum('total_amount');
            $pending = FeeStructure::where('is_active', true)
                ->whereNotIn('id', $payments->pluck('fee_structure_id'))
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'payments' => $payments,
                    'pending_fees' => $pending,
                    'total_paid' => $totalPaid,
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
