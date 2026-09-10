<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\Notification;
use App\Models\Announcement;
use App\Models\Circular;
use App\Services\AcademicYearContext;
use App\Services\AssignmentRecipientResolver;
use App\Services\InboxNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
            $user = $request->user();
            $query = Notification::query();

            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            $isStaff = $user && in_array($user->role, ['SuperAdmin', 'BranchAdmin'], true);
            $staffScope = $isStaff && $request->get('scope') === 'staff';

            if ($staffScope) {
                if ($request->filled('user_id')) {
                    $query->where('user_id', (int) $request->user_id);
                }
            } else {
                $query->where('user_id', $user->id);
            }

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->get('source')) {
                $query->where('metadata->source', strip_tags($request->source));
            }

            $status = $request->get('status', 'unread');
            if ($status === 'unread') {
                $query->whereNull('read_at');
            } elseif ($status === 'read') {
                $query->whereNotNull('read_at');
            } elseif ($status !== 'all' && $request->filled('status')) {
                $query->where('status', $status);
            }

            if ($request->filled('priority')) {
                $query->where('priority', $request->priority);
            }

            $period = $request->get('period');
            if ($period === 'today') {
                $query->whereDate('created_at', now()->toDateString());
            } elseif ($period === 'older') {
                $query->whereDate('created_at', '<', now()->toDateString());
            }

            $query->orderBy('created_at', 'desc');

            $perPage = $request->get('per_page', 15);
            $notifications = $query->with(['branch', 'user', 'createdBy'])->paginate($perPage);

            $items = collect($notifications->items())->map(function ($notification) use ($user) {
                return $this->presentInboxItem($notification, $user);
            })->values();

            return response()->json([
                'success' => true,
                'data' => $items,
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'last_page' => $notifications->lastPage(),
                    'has_more_pages' => $notifications->hasMorePages(),
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

    public function getNotificationReceipts(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user || !in_array($user->role, ['Teacher', 'BranchAdmin', 'SuperAdmin', 'Staff'], true)) {
                return response()->json(['success' => false, 'message' => 'Not allowed'], 403);
            }

            $groupKey = $request->get('group_key');
            if (!$groupKey && $request->filled('assignment_id')) {
                $groupKey = 'assignment:' . (int) $request->assignment_id;
            }
            if (!$groupKey) {
                return response()->json(['success' => false, 'message' => 'group_key is required'], 422);
            }

            $sample = Notification::query()
                ->where('metadata->group_key', $groupKey)
                ->first();
            if (!$sample) {
                return response()->json(['success' => false, 'message' => 'No recipients found'], 404);
            }
            if (!$this->canAccessBranch($request, (int) $sample->branch_id)) {
                return response()->json(['success' => false, 'message' => 'Not allowed'], 403);
            }

            $isAdmin = in_array($user->role, ['SuperAdmin', 'BranchAdmin'], true);
            $involved = Notification::query()
                ->where('metadata->group_key', $groupKey)
                ->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhere('created_by', $user->id);
                })
                ->exists();
            if (!$isAdmin && !$involved) {
                return response()->json(['success' => false, 'message' => 'Not allowed'], 403);
            }

            $data = app(InboxNotificationService::class)->receipts($groupKey, (int) $sample->branch_id);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('Notification receipts error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to load views'], 500);
        }
    }

    private function presentInboxItem(Notification $notification, $user): array
    {
        $row = $notification->toArray();
        $meta = is_array($notification->metadata) ? $notification->metadata : [];
        $source = $meta['source'] ?? null;
        $groupKey = $meta['group_key'] ?? null;
        if (!$groupKey && !empty($meta['assignment_id'])) {
            $groupKey = 'assignment:' . $meta['assignment_id'];
        }
            $canViewReceipts = $user && in_array($user->role, ['Teacher', 'BranchAdmin', 'SuperAdmin', 'Staff'], true)
            && in_array($source, ['assignment', 'attendance', 'custom', 'attendance_notify'], true);

        $details = app(InboxNotificationService::class)->detailsForMeta($meta);
        $row['is_read'] = $notification->read_at !== null;
        $row['date'] = optional($notification->created_at)?->toDateTimeString();
        $row['source'] = $source;
        $row['event'] = $meta['event'] ?? null;
        $row['assignment_id'] = $meta['assignment_id'] ?? null;
        $row['group_key'] = $groupKey;
        $row['action_url'] = $notification->action_url;
        $row['can_view_receipts'] = $canViewReceipts;
        $row['description'] = $details['description'] ?: $notification->message;
        $row['optional_description'] = $details['optional_description'];
        $row['attachments'] = $details['attachments'];
        $row['grade'] = $meta['grade'] ?? null;
        $row['section'] = $meta['section'] ?? null;
        $row['student_count'] = isset($meta['student_count']) ? (int) $meta['student_count'] : null;
        $row['audience'] = $this->audienceLabel($meta['grade'] ?? null, $meta['section'] ?? null);

        return $row;
    }

    public function broadcastNotification(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user || !in_array($user->role, ['Teacher', 'BranchAdmin', 'SuperAdmin', 'Staff'], true)) {
                return response()->json(['success' => false, 'message' => 'Not allowed'], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'optional_description' => 'nullable|string',
                'grade' => 'required|string|max:50',
                'section' => 'required|string|max:50',
                'audience_mode' => 'required|in:all,custom',
                'student_ids' => 'nullable|array',
                'student_ids.*' => 'integer',
                'attachments' => 'nullable|array',
                'branch_id' => 'nullable|exists:branches,id',
            ]);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $branchId = $this->resolveWritableBranchId($request, $request->branch_id);
            if (!$branchId || !$this->canManageBranch($request, $branchId)) {
                return response()->json(['success' => false, 'message' => 'Not allowed'], 403);
            }

            $grade = strip_tags($request->grade);
            $section = strip_tags($request->section);
            $title = strip_tags($request->title);
            $description = strip_tags($request->description);
            $optional = $request->optional_description ? strip_tags($request->optional_description) : null;

            $academicYearId = app(AcademicYearContext::class)->id(false);
            $studentIds = app(AssignmentRecipientResolver::class)->resolveStudentIds(
                $branchId,
                $grade,
                $section,
                $academicYearId,
                $request->audience_mode,
                $request->student_ids
            );
            $userIds = app(AssignmentRecipientResolver::class)->studentUserIds($studentIds);
            if ($userIds === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'No students with login accounts found for this class and section.',
                ], 422);
            }

            $groupKey = 'custom:' . Str::uuid()->toString();
            $sentAt = now();
            $metadata = [
                'source' => 'custom',
                'event' => 'broadcast',
                'group_key' => $groupKey,
                'grade' => $grade,
                'section' => $section,
                'audience_mode' => $request->audience_mode,
                'student_count' => count($userIds),
                'description' => $description,
                'optional_description' => $optional,
            ];

            $inbox = app(InboxNotificationService::class);
            $inbox->insertForUsers(
                $userIds,
                $branchId,
                $title,
                $description,
                $metadata,
                $user->id,
                'Info',
                'Medium'
            );

            $campaignId = (int) Notification::withoutTenantScope()
                ->where('metadata->group_key', $groupKey)
                ->min('id');
            if ($campaignId > 0) {
                $inbox->syncNotificationAttachments($campaignId, $request->attachments ?? []);
                $rows = Notification::withoutTenantScope()
                    ->where('metadata->group_key', $groupKey)
                    ->get();
                foreach ($rows as $row) {
                    $meta = is_array($row->metadata) ? $row->metadata : [];
                    $meta['campaign_id'] = $campaignId;
                    $row->metadata = $meta;
                    $row->save();
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification sent successfully',
                'data' => [
                    'group_key' => $groupKey,
                    'grade' => $grade,
                    'section' => $section,
                    'class' => $this->audienceLabel($grade, $section),
                    'student_count' => count($userIds),
                    'sent_at' => $sentAt->toDateTimeString(),
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Broadcast notification error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to send notification'], 500);
        }
    }

    public function getSentNotifications(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Not allowed'], 403);
            }

            $query = Notification::query()
                ->where('created_by', $user->id)
                ->where('metadata->source', 'custom');

            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            $rows = $query->orderByDesc('created_at')->limit(400)->get();
            $inbox = app(InboxNotificationService::class);
            $campaigns = $rows
                ->groupBy(fn (Notification $row) => data_get($row->metadata, 'group_key') ?: (string) $row->id)
                ->map(function ($group) use ($inbox) {
                    /** @var Notification $first */
                    $first = $group->sortBy('id')->first();
                    $meta = is_array($first->metadata) ? $first->metadata : [];
                    $details = $inbox->detailsForMeta($meta);
                    $grade = $meta['grade'] ?? null;
                    $section = $meta['section'] ?? null;

                    return [
                        'id' => $first->id,
                        'group_key' => $meta['group_key'] ?? null,
                        'title' => $first->title,
                        'message' => $first->message,
                        'description' => $details['description'] ?: $first->message,
                        'optional_description' => $details['optional_description'],
                        'attachments' => $details['attachments'],
                        'grade' => $grade,
                        'section' => $section,
                        'audience' => $this->audienceLabel($grade, $section),
                        'student_count' => (int) ($meta['student_count'] ?? $group->count()),
                        'status' => 'Sent',
                        'sent_at' => optional($first->sent_at ?? $first->created_at)?->toDateTimeString(),
                        'date' => optional($first->created_at)?->toDateTimeString(),
                        'source' => 'custom',
                        'can_view_receipts' => true,
                    ];
                })
                ->values();

            return response()->json(['success' => true, 'data' => $campaigns]);
        } catch (\Throwable $e) {
            Log::error('Sent notifications error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to load sent notifications'], 500);
        }
    }

    private function audienceLabel($grade, $section): ?string
    {
        if (!$grade && !$section) {
            return null;
        }
        $label = $grade ? ('Grade ' . $grade) : 'Class';
        if ($section) {
            $label .= ' - ' . $section;
        }

        return $label;
    }

    private function resolveWritableBranchId(Request $request, $requested): ?int
    {
        if ($requested) {
            return (int) $requested;
        }
        $default = $this->getDefaultBranchId($request);
        if ($default) {
            return (int) $default;
        }
        $user = $request->user();

        return $user?->branch_id ? (int) $user->branch_id : null;
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
    public function markAsRead(Request $request, $id)
    {
        try {
            $user = $request->user();
            $notification = Notification::findOrFail($id);

            $isOwner = (int) $notification->user_id === (int) $user->id;
            $isStaff = in_array($user->role, ['SuperAdmin', 'BranchAdmin'], true)
                && $this->canAccessBranch($request, (int) $notification->branch_id);

            if (!$isOwner && !$isStaff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found'
                ], 404);
            }

            $notification->update([
                'read_at' => now(),
                'status' => 'Read'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'data' => $notification->fresh()
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Mark notification as read error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notification as read',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Mark every unread notification for the current user as read.
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $user = $request->user();
            $updated = Notification::query()
                ->where('user_id', $user->id)
                ->whereNull('read_at')
                ->update([
                    'read_at' => now(),
                    'status' => 'Read',
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
                'data' => ['updated' => $updated],
            ]);
        } catch (\Exception $e) {
            Log::error('Mark all notifications as read error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notifications as read',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
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

            // OPTIMIZED Search filter - prefix search for better index usage
            if ($request->has('search') && !empty($request->search)) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "{$search}%")
                      ->orWhere('content', 'like', "{$search}%");
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

            // OPTIMIZED Search filter - prefix search for better index usage
            if ($request->has('search') && !empty($request->search)) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "{$search}%")
                      ->orWhere('content', 'like', "{$search}%")
                      ->orWhere('circular_number', 'like', "{$search}%");
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

