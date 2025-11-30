<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\Event;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class EventController extends Controller
{
    use PaginatesAndSorts;
    // ==================== EVENTS MANAGEMENT ====================
    
    /**
     * Get all events
     */
    public function index(Request $request)
    {
        try {
            $query = Event::with(['branch', 'creator']);

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('event_type')) {
                $query->where('event_type', $request->event_type);
            }

            if ($request->has('from_date')) {
                $query->whereDate('event_date', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate('event_date', '<=', $request->to_date);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // OPTIMIZED Search filter - prefix search for better index usage
            if ($request->has('search') && !empty($request->search)) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "{$search}%")
                      ->orWhere('event_type', 'like', "{$search}%")
                      ->orWhere('venue', 'like', "{$search}%");
                });
            }

            // Define sortable columns
            $sortableColumns = [
                'id',
                'title',
                'event_type',
                'event_date',
                'start_time',
                'venue',
                'is_active',
                'created_at'
            ];

            // Apply pagination and sorting (default: 25 per page, sorted by event_date asc)
            $events = $this->paginateAndSort($query, $request, $sortableColumns, 'event_date', 'asc');

            return response()->json([
                'success' => true,
                'message' => 'Events retrieved successfully',
                'data' => $events->items(),
                'meta' => [
                    'current_page' => $events->currentPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                    'last_page' => $events->lastPage(),
                    'from' => $events->firstItem(),
                    'to' => $events->lastItem(),
                    'has_more_pages' => $events->hasMorePages()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get events error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch events',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Store new event
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|exists:branches,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'event_type' => 'required|string|in:Academic,Sports,Cultural,Meeting,Holiday,Other',
                'event_date' => 'required|date',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i|after:start_time',
                'venue' => 'nullable|string|max:255',
                'organizer' => 'nullable|string|max:255',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $event = Event::create([
                'branch_id' => $request->branch_id,
                'title' => strip_tags($request->title),
                'description' => strip_tags($request->description ?? ''),
                'event_type' => $request->event_type,
                'event_date' => $request->event_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'venue' => strip_tags($request->venue ?? ''),
                'organizer' => strip_tags($request->organizer ?? ''),
                'is_active' => $request->is_active ?? true,
                'created_by' => $request->user()->id
            ]);

            DB::commit();

            Log::info('Event created', ['event_id' => $event->id]);

            return response()->json([
                'success' => true,
                'message' => 'Event created successfully',
                'data' => $event->load(['branch', 'creator'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create event error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create event',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get specific event
     */
    public function show(string $id)
    {
        try {
            $event = Event::with(['branch', 'creator'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $event
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }
    }

    /**
     * Update event
     */
    public function update(Request $request, string $id)
    {
        DB::beginTransaction();
        try {
            $event = Event::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'string|max:255',
                'description' => 'nullable|string|max:1000',
                'event_type' => 'string|in:Academic,Sports,Cultural,Meeting,Holiday,Other',
                'event_date' => 'date',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i',
                'venue' => 'nullable|string|max:255',
                'organizer' => 'nullable|string|max:255',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = [];
            if ($request->has('title')) $updateData['title'] = strip_tags($request->title);
            if ($request->has('description')) $updateData['description'] = strip_tags($request->description);
            if ($request->has('event_type')) $updateData['event_type'] = $request->event_type;
            if ($request->has('event_date')) $updateData['event_date'] = $request->event_date;
            if ($request->has('start_time')) $updateData['start_time'] = $request->start_time;
            if ($request->has('end_time')) $updateData['end_time'] = $request->end_time;
            if ($request->has('venue')) $updateData['venue'] = strip_tags($request->venue);
            if ($request->has('organizer')) $updateData['organizer'] = strip_tags($request->organizer);
            if ($request->has('is_active')) $updateData['is_active'] = $request->is_active;
            
            $updateData['updated_by'] = $request->user()->id;

            $event->update($updateData);

            DB::commit();

            Log::info('Event updated', ['event_id' => $event->id]);

            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully',
                'data' => $event->load(['branch', 'creator'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update event error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update event',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Delete event
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {
            $event = Event::findOrFail($id);
            $event->delete();
            DB::commit();

            Log::info('Event deleted', ['event_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Event deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete event error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete event',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get upcoming events
     */
    public function getUpcoming()
    {
        try {
            $events = Event::with(['branch', 'creator'])
                ->where('event_date', '>=', Carbon::today())
                ->where('is_active', true)
                ->orderBy('event_date', 'asc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $events
            ]);

        } catch (\Exception $e) {
            Log::error('Get upcoming events error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch upcoming events',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get events by type - OPTIMIZED with pagination
     */
    public function getByType(Request $request, string $type)
    {
        try {
            $query = Event::select([
                'id', 'branch_id', 'title', 'description', 'event_type', 
                'event_date', 'start_time', 'end_time', 'venue', 'organizer',
                'is_active', 'created_by', 'created_at', 'updated_at'
            ])
            ->with(['branch:id,name,code', 'creator:id,first_name,last_name'])
                ->where('event_type', $type)
                ->where('is_active', true);

            // OPTIMIZED Search filter - prefix search for better index usage
            if ($request->has('search') && !empty($request->search)) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "{$search}%")
                      ->orWhere('venue', 'like', "{$search}%");
                });
            }

            // Define sortable columns
            $sortableColumns = [
                'id',
                'title',
                'event_date',
                'start_time',
                'venue',
                'created_at'
            ];

            // Apply pagination and sorting (default: 25 per page, sorted by event_date desc)
            $events = $this->paginateAndSort($query, $request, $sortableColumns, 'event_date', 'desc');

            return response()->json([
                'success' => true,
                'data' => $events->items(),
                'meta' => [
                    'current_page' => $events->currentPage(),
                    'per_page' => $events->perPage(),
                    'total' => $events->total(),
                    'last_page' => $events->lastPage(),
                    'from' => $events->firstItem(),
                    'to' => $events->lastItem(),
                    'has_more_pages' => $events->hasMorePages()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get events by type error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch events',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    // ==================== HOLIDAYS MANAGEMENT ====================

    /**
     * Get holidays by year
     */
    public function getHolidaysByYear(int $year)
    {
        try {
            $holidays = Holiday::with(['branch', 'creator'])
                ->whereYear('holiday_date', $year)
                ->orderBy('holiday_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $holidays
            ]);

        } catch (\Exception $e) {
            Log::error('Get holidays error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch holidays',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}
