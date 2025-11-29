<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\Notification;
use App\Models\Announcement;
use App\Models\Circular;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommunicationController extends Controller
{
    use PaginatesAndSorts;

    // ==================== NOTIFICATIONS ====================

    /**
     * Get all notifications
     */
    public function getNotifications(Request $request)
    {
        try {
            $query = Notification::query();

            // Branch filtering
            $user = $request->user();
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            // User-specific notifications
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            // Filters
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('status')) {
                if ($request->status === 'unread') {
                    $query->whereNull('read_at');
                } else {
                    $query->where('status', $request->status);
                }
            }

            if ($request->has('priority')) {
                $query->where('priority', $request->priority);
            }

            // Sorting
            $query->orderBy('created_at', 'desc');

            // Pagination
            $perPage = $request->get('per_page', 15);
            $notifications = $query->with(['branch', 'user', 'createdBy'])->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $notifications->items(),
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'last_page' => $notifications->lastPage()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get notifications error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Create notification
     */
    public function createNotification(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'message' => 'required|string',
                'type' => 'nullable|in:Info,Warning,Error,Success,Alert',
                'priority' => 'nullable|in:Low,Medium,High,Urgent',
                'user_id' => 'nullable|exists:users,id',
                'branch_id' => 'nullable|exists:branches,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $notification = Notification::create([
                'branch_id' => $request->branch_id,
                'user_id' => $request->user_id,
                'title' => strip_tags($request->title),
                'message' => strip_tags($request->message),
                'type' => $request->type ?? 'Info',
                'priority' => $request->priority ?? 'Medium',
                'status' => 'Pending',
                'action_url' => $request->action_url,
                'metadata' => $request->metadata ?? [],
                'created_by' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification created successfully',
                'data' => $notification
            ], 201);

        } catch (\Exception $e) {
            Log::error('Create notification error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create notification',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($id)
    {
        try {
            $notification = Notification::findOrFail($id);
            $notification->update([
                'read_at' => now(),
                'status' => 'Read'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'data' => $notification->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Mark notification as read error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    // ==================== ANNOUNCEMENTS ====================

    /**
     * Get all announcements
     */
    public function getAnnouncements(Request $request)
    {
        try {
            $query = Announcement::query();

            // Branch filtering
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            // Filters
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('published')) {
                if ($request->boolean('published')) {
                    $query->published();
                }
            }

            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortableColumns = ['title', 'start_date', 'end_date', 'type', 'priority'];
            $query = $this->applySorting($query, $request, $sortableColumns, 'created_at', 'desc');

            // Pagination
            $perPage = $request->get('per_page', 15);
            $announcements = $query->with(['branch', 'createdBy', 'updatedBy'])->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $announcements->items(),
                'meta' => [
                    'current_page' => $announcements->currentPage(),
                    'per_page' => $announcements->perPage(),
                    'total' => $announcements->total(),
                    'last_page' => $announcements->lastPage()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get announcements error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch announcements',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Create announcement
     */
    public function createAnnouncement(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'nullable|exists:branches,id',
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'type' => 'nullable|in:General,Academic,Event,Holiday,Emergency,Other',
                'target_audience' => 'nullable|array',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'priority' => 'nullable|in:Low,Medium,High,Urgent',
                'is_published' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $announcement = Announcement::create([
                'branch_id' => $request->branch_id,
                'title' => strip_tags($request->title),
                'content' => strip_tags($request->content),
                'type' => $request->type ?? 'General',
                'target_audience' => $request->target_audience ?? ['All'],
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'priority' => $request->priority ?? 'Medium',
                'is_published' => $request->boolean('is_published', false),
                'published_at' => $request->boolean('is_published') ? now() : null,
                'attachments' => $request->attachments ?? [],
                'created_by' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Announcement created successfully',
                'data' => $announcement
            ], 201);

        } catch (\Exception $e) {
            Log::error('Create announcement error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create announcement',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get single announcement
     */
    public function getAnnouncement($id)
    {
        try {
            $announcement = Announcement::with(['branch', 'createdBy', 'updatedBy'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $announcement
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Announcement not found'
            ], 404);
        }
    }

    /**
     * Update announcement
     */
    public function updateAnnouncement(Request $request, $id)
    {
        try {
            $announcement = Announcement::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'content' => 'sometimes|required|string',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after:start_date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $announcement->update(array_merge(
                $request->only([
                    'title', 'content', 'type', 'target_audience',
                    'start_date', 'end_date', 'priority', 'attachments'
                ]),
                [
                    'is_published' => $request->boolean('is_published', $announcement->is_published),
                    'published_at' => $request->boolean('is_published') && !$announcement->is_published ? now() : $announcement->published_at,
                    'updated_by' => $request->user()->id
                ]
            ));

            return response()->json([
                'success' => true,
                'message' => 'Announcement updated successfully',
                'data' => $announcement->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Update announcement error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update announcement',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Delete announcement
     */
    public function deleteAnnouncement($id)
    {
        try {
            $announcement = Announcement::findOrFail($id);
            $announcement->delete();

            return response()->json([
                'success' => true,
                'message' => 'Announcement deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Delete announcement error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete announcement',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    // ==================== CIRCULARS ====================

    /**
     * Get all circulars
     */
    public function getCirculars(Request $request)
    {
        try {
            $query = Circular::query();

            // Branch filtering
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            // Filters
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('published')) {
                if ($request->boolean('published')) {
                    $query->published();
                }
            }

            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('content', 'like', "%{$search}%")
                      ->orWhere('circular_number', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortableColumns = ['circular_number', 'title', 'issue_date', 'effective_date', 'type'];
            $query = $this->applySorting($query, $request, $sortableColumns, 'issue_date', 'desc');

            // Pagination
            $perPage = $request->get('per_page', 15);
            $circulars = $query->with(['branch', 'createdBy', 'updatedBy'])->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $circulars->items(),
                'meta' => [
                    'current_page' => $circulars->currentPage(),
                    'per_page' => $circulars->perPage(),
                    'total' => $circulars->total(),
                    'last_page' => $circulars->lastPage()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get circulars error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch circulars',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Create circular
     */
    public function createCircular(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'nullable|exists:branches,id',
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'type' => 'nullable|in:Notice,Order,Instruction,Information,Other',
                'target_audience' => 'nullable|array',
                'issue_date' => 'required|date',
                'effective_date' => 'required|date',
                'expiry_date' => 'required|date|after:effective_date',
                'priority' => 'nullable|in:Low,Medium,High,Urgent',
                'requires_acknowledgment' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Generate circular number
            $branch = $request->branch_id ? \App\Models\Branch::find($request->branch_id) : null;
            $year = date('Y');
            $lastCircular = Circular::whereYear('issue_date', $year)
                ->orderBy('id', 'desc')
                ->first();
            
            $sequence = $lastCircular ? (int)substr($lastCircular->circular_number, -4) + 1 : 1;
            $circularNumber = ($branch ? $branch->code . '/' : '') . 'CIR/' . $year . '/' . str_pad($sequence, 4, '0', STR_PAD_LEFT);

            $circular = Circular::create([
                'branch_id' => $request->branch_id,
                'circular_number' => $circularNumber,
                'title' => strip_tags($request->title),
                'content' => strip_tags($request->content),
                'type' => $request->type ?? 'Notice',
                'target_audience' => $request->target_audience ?? ['All'],
                'issue_date' => $request->issue_date,
                'effective_date' => $request->effective_date,
                'expiry_date' => $request->expiry_date,
                'priority' => $request->priority ?? 'Medium',
                'requires_acknowledgment' => $request->boolean('requires_acknowledgment', false),
                'is_published' => $request->boolean('is_published', false),
                'published_at' => $request->boolean('is_published') ? now() : null,
                'attachments' => $request->attachments ?? [],
                'created_by' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Circular created successfully',
                'data' => $circular
            ], 201);

        } catch (\Exception $e) {
            Log::error('Create circular error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create circular',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get single circular
     */
    public function getCircular($id)
    {
        try {
            $circular = Circular::with(['branch', 'createdBy', 'updatedBy'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $circular
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Circular not found'
            ], 404);
        }
    }

    /**
     * Update circular
     */
    public function updateCircular(Request $request, $id)
    {
        try {
            $circular = Circular::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'content' => 'sometimes|required|string',
                'effective_date' => 'sometimes|date',
                'expiry_date' => 'sometimes|date|after:effective_date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $circular->update(array_merge(
                $request->only([
                    'title', 'content', 'type', 'target_audience',
                    'issue_date', 'effective_date', 'expiry_date', 'priority',
                    'requires_acknowledgment', 'attachments'
                ]),
                [
                    'is_published' => $request->boolean('is_published', $circular->is_published),
                    'published_at' => $request->boolean('is_published') && !$circular->is_published ? now() : $circular->published_at,
                    'updated_by' => $request->user()->id
                ]
            ));

            return response()->json([
                'success' => true,
                'message' => 'Circular updated successfully',
                'data' => $circular->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Update circular error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update circular',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Delete circular
     */
    public function deleteCircular($id)
    {
        try {
            $circular = Circular::findOrFail($id);
            $circular->delete();

            return response()->json([
                'success' => true,
                'message' => 'Circular deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Delete circular error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete circular',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Acknowledge circular
     */
    public function acknowledgeCircular(Request $request, $id)
    {
        try {
            $circular = Circular::findOrFail($id);
            $user = $request->user();

            DB::table('circular_acknowledgments')->updateOrInsert(
                [
                    'circular_id' => $circular->id,
                    'user_id' => $user->id
                ],
                [
                    'acknowledged_at' => now(),
                    'remarks' => $request->remarks,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Circular acknowledged successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Acknowledge circular error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to acknowledge circular',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}

