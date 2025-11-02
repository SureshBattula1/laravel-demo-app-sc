<?php

namespace App\Http\Controllers;

use App\Models\BranchTransfer;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class BranchTransferController extends Controller
{
    /**
     * Get all branch transfers
     */
    public function index(Request $request)
    {
        try {
            $query = BranchTransfer::with([
                'user:id,first_name,last_name,email,role',
                'fromBranch:id,name,code',
                'toBranch:id,name,code',
                'requester:id,first_name,last_name',
                'approver:id,first_name,last_name'
            ]);

            $user = Auth::user();

            // Filter based on user role
            if ($user->role === 'BranchAdmin') {
                $branch = Branch::find($user->branch_id);
                $branchIds = $branch ? $branch->getDescendantIds() : [$user->branch_id];
                
                $query->where(function($q) use ($branchIds) {
                    $q->whereIn('from_branch_id', $branchIds)
                      ->orWhereIn('to_branch_id', $branchIds);
                });
            }

            // Filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('transfer_type')) {
                $query->where('transfer_type', $request->transfer_type);
            }

            if ($request->has('from_branch_id')) {
                $query->where('from_branch_id', $request->from_branch_id);
            }

            if ($request->has('to_branch_id')) {
                $query->where('to_branch_id', $request->to_branch_id);
            }

            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('date_from')) {
                $query->where('transfer_date', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('transfer_date', '<=', $request->date_to);
            }

            $transfers = $query->orderBy('transfer_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $transfers
            ]);

        } catch (\Exception $e) {
            Log::error('Get branch transfers error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branch transfers'
            ], 500);
        }
    }

    /**
     * Create a new branch transfer request
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'from_branch_id' => 'required|exists:branches,id',
                'to_branch_id' => 'required|exists:branches,id|different:from_branch_id',
                'transfer_type' => 'required|in:Student,Teacher,Staff',
                'transfer_date' => 'required|date',
                'effective_date' => 'nullable|date|after_or_equal:transfer_date',
                'reason' => 'required|string|max:1000',
                'remarks' => 'nullable|string|max:500',
                'metadata' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate user belongs to source branch
            $user = User::findOrFail($request->user_id);
            if ($user->branch_id != $request->from_branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User does not belong to the source branch'
                ], 400);
            }

            // Validate user role matches transfer type
            if ($request->transfer_type === 'Student' && $user->role !== 'Student') {
                return response()->json([
                    'success' => false,
                    'message' => 'Transfer type does not match user role'
                ], 400);
            }

            // Check for existing pending transfer
            $existingTransfer = BranchTransfer::where('user_id', $request->user_id)
                ->whereIn('status', ['Pending', 'Approved'])
                ->first();

            if ($existingTransfer) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already has a pending or approved transfer request'
                ], 400);
            }

            // Validate target branch capacity for students
            if ($request->transfer_type === 'Student') {
                $toBranch = Branch::find($request->to_branch_id);
                if ($toBranch->current_enrollment >= $toBranch->total_capacity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Target branch has reached maximum capacity'
                    ], 400);
                }
            }

            DB::beginTransaction();

            $transfer = BranchTransfer::create([
                'user_id' => $request->user_id,
                'from_branch_id' => $request->from_branch_id,
                'to_branch_id' => $request->to_branch_id,
                'transfer_type' => $request->transfer_type,
                'transfer_date' => $request->transfer_date,
                'effective_date' => $request->effective_date ?? $request->transfer_date,
                'reason' => strip_tags($request->reason),
                'remarks' => $request->remarks ? strip_tags($request->remarks) : null,
                'metadata' => $request->metadata,
                'status' => 'Pending',
                'requested_by' => Auth::id()
            ]);

            DB::commit();

            Log::info('Branch transfer requested', [
                'transfer_id' => $transfer->id,
                'user_id' => $request->user_id,
                'requested_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transfer request created successfully',
                'data' => $transfer->load(['user', 'fromBranch', 'toBranch', 'requester'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create branch transfer error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create transfer request',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Show transfer details
     */
    public function show($id)
    {
        try {
            $transfer = BranchTransfer::with([
                'user',
                'fromBranch',
                'toBranch',
                'requester',
                'approver'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $transfer
            ]);

        } catch (\Exception $e) {
            Log::error('Get transfer details error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Transfer not found'
            ], 404);
        }
    }

    /**
     * Approve transfer request
     */
    public function approve(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'remarks' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $transfer = BranchTransfer::findOrFail($id);

            if (!$transfer->canBeApproved()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transfer request cannot be approved'
                ], 400);
            }

            $user = Auth::user();

            // Check authorization
            if (!in_array($user->role, ['SuperAdmin', 'BranchAdmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to approve transfers'
                ], 403);
            }

            DB::beginTransaction();

            $transfer->update([
                'status' => 'Approved',
                'approved_by' => $user->id,
                'remarks' => $request->remarks
            ]);

            DB::commit();

            Log::info('Branch transfer approved', [
                'transfer_id' => $id,
                'approved_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transfer request approved successfully',
                'data' => $transfer->fresh(['user', 'fromBranch', 'toBranch', 'approver'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Approve transfer error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve transfer request'
            ], 500);
        }
    }

    /**
     * Reject transfer request
     */
    public function reject(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'remarks' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $transfer = BranchTransfer::findOrFail($id);

            if (!$transfer->canBeApproved()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transfer request cannot be rejected'
                ], 400);
            }

            $user = Auth::user();

            // Check authorization
            if (!in_array($user->role, ['SuperAdmin', 'BranchAdmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to reject transfers'
                ], 403);
            }

            DB::beginTransaction();

            $transfer->update([
                'status' => 'Rejected',
                'approved_by' => $user->id,
                'remarks' => strip_tags($request->remarks)
            ]);

            DB::commit();

            Log::info('Branch transfer rejected', [
                'transfer_id' => $id,
                'rejected_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transfer request rejected',
                'data' => $transfer->fresh(['user', 'fromBranch', 'toBranch', 'approver'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Reject transfer error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject transfer request'
            ], 500);
        }
    }

    /**
     * Complete/Execute transfer (move user to new branch)
     */
    public function complete($id)
    {
        try {
            $transfer = BranchTransfer::with(['user', 'fromBranch', 'toBranch'])->findOrFail($id);

            if ($transfer->status !== 'Approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only approved transfers can be completed'
                ], 400);
            }

            // Check if effective date has passed
            if ($transfer->effective_date && Carbon::parse($transfer->effective_date)->isFuture()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transfer effective date has not arrived yet'
                ], 400);
            }

            DB::beginTransaction();

            // Update user's branch
            $transfer->user->update([
                'branch_id' => $transfer->to_branch_id
            ]);

            // Update branch enrollments for students
            if ($transfer->transfer_type === 'Student') {
                $transfer->fromBranch->decrement('current_enrollment');
                $transfer->toBranch->increment('current_enrollment');
            }

            // Mark transfer as completed
            $transfer->update([
                'status' => 'Completed'
            ]);

            DB::commit();

            Log::info('Branch transfer completed', [
                'transfer_id' => $id,
                'user_id' => $transfer->user_id,
                'completed_by' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transfer completed successfully',
                'data' => $transfer->fresh(['user', 'fromBranch', 'toBranch'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Complete transfer error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete transfer'
            ], 500);
        }
    }

    /**
     * Cancel transfer request
     */
    public function cancel($id)
    {
        try {
            $transfer = BranchTransfer::findOrFail($id);

            if (!$transfer->canBeCancelled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transfer cannot be cancelled'
                ], 400);
            }

            $user = Auth::user();

            // Only requester or admin can cancel
            if ($transfer->requested_by !== $user->id && !in_array($user->role, ['SuperAdmin', 'BranchAdmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to cancel this transfer'
                ], 403);
            }

            DB::beginTransaction();

            $transfer->update(['status' => 'Cancelled']);

            DB::commit();

            Log::info('Branch transfer cancelled', [
                'transfer_id' => $id,
                'cancelled_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transfer request cancelled'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Cancel transfer error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel transfer'
            ], 500);
        }
    }

    /**
     * Get transfer statistics
     */
    public function getStatistics(Request $request)
    {
        try {
            $branchId = $request->input('branch_id');
            $dateFrom = $request->input('date_from', Carbon::now()->subMonth());
            $dateTo = $request->input('date_to', Carbon::now());

            $query = BranchTransfer::whereBetween('transfer_date', [$dateFrom, $dateTo]);

            if ($branchId) {
                $query->where(function($q) use ($branchId) {
                    $q->where('from_branch_id', $branchId)
                      ->orWhere('to_branch_id', $branchId);
                });
            }

            $stats = [
                'total_transfers' => $query->count(),
                'by_status' => [
                    'pending' => (clone $query)->where('status', 'Pending')->count(),
                    'approved' => (clone $query)->where('status', 'Approved')->count(),
                    'rejected' => (clone $query)->where('status', 'Rejected')->count(),
                    'completed' => (clone $query)->where('status', 'Completed')->count(),
                    'cancelled' => (clone $query)->where('status', 'Cancelled')->count()
                ],
                'by_type' => [
                    'students' => (clone $query)->where('transfer_type', 'Student')->count(),
                    'teachers' => (clone $query)->where('transfer_type', 'Teacher')->count(),
                    'staff' => (clone $query)->where('transfer_type', 'Staff')->count()
                ]
            ];

            if ($branchId) {
                $stats['by_direction'] = [
                    'incoming' => BranchTransfer::where('to_branch_id', $branchId)
                        ->whereBetween('transfer_date', [$dateFrom, $dateTo])
                        ->count(),
                    'outgoing' => BranchTransfer::where('from_branch_id', $branchId)
                        ->whereBetween('transfer_date', [$dateFrom, $dateTo])
                        ->count()
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Get transfer statistics error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transfer statistics'
            ], 500);
        }
    }
}

