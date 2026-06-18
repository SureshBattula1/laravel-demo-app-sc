<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\TransportRoute;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TransportController extends Controller
{
    use PaginatesAndSorts;
    // ==================== ROUTES MANAGEMENT ====================
    
    /**
     * Get all transport routes
     */
    public function index(Request $request)
    {
        try {
            $query = TransportRoute::with(['branch', 'vehicle', 'driver', 'creator']);

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // OPTIMIZED Search filter - prefix search for better index usage
            if ($request->has('search') && !empty($request->search)) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('route_name', 'like', "{$search}%")
                      ->orWhere('route_number', 'like', "{$search}%")
                      ->orWhere('start_point', 'like', "{$search}%")
                      ->orWhere('end_point', 'like', "{$search}%");
                });
            }

            // Define sortable columns
            $sortableColumns = [
                'id',
                'route_number',
                'route_name',
                'start_point',
                'end_point',
                'fare',
                'is_active',
                'created_at'
            ];

            // Apply pagination and sorting (default: 25 per page, sorted by route_number asc)
            $routes = $this->paginateAndSort($query, $request, $sortableColumns, 'route_number', 'asc');

            return response()->json([
                'success' => true,
                'message' => 'Routes retrieved successfully',
                'data' => $routes->items(),
                'meta' => [
                    'current_page' => $routes->currentPage(),
                    'per_page' => $routes->perPage(),
                    'total' => $routes->total(),
                    'last_page' => $routes->lastPage(),
                    'from' => $routes->firstItem(),
                    'to' => $routes->lastItem(),
                    'has_more_pages' => $routes->hasMorePages()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get routes error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch routes',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Store new route
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|exists:branches,id',
                'route_name' => 'required|string|max:255',
                'route_number' => 'required|string|max:50|unique:transport_routes',
                'start_point' => 'required|string|max:255',
                'end_point' => 'required|string|max:255',
                'stops' => 'nullable|json',
                'vehicle_id' => 'nullable|exists:vehicles,id',
                'driver_id' => 'nullable|exists:users,id',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i|after:start_time',
                'fare' => 'nullable|numeric|min:0',
                'distance_km' => 'nullable|numeric|min:0',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $route = TransportRoute::create([
                'branch_id' => $request->branch_id,
                'route_name' => strip_tags($request->route_name),
                'route_number' => $request->route_number,
                'start_point' => strip_tags($request->start_point),
                'end_point' => strip_tags($request->end_point),
                'stops' => $request->stops,
                'vehicle_id' => $request->vehicle_id,
                'driver_id' => $request->driver_id,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'fare' => $request->fare ?? 0,
                'distance_km' => $request->distance_km,
                'is_active' => $request->is_active ?? true,
                'created_by' => $request->user()->id
            ]);

            DB::commit();

            Log::info('Route created', ['route_id' => $route->id]);

            return response()->json([
                'success' => true,
                'message' => 'Route created successfully',
                'data' => $route->load(['branch', 'vehicle', 'driver'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create route error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create route',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get specific route
     */
    public function show(string $id)
    {
        try {
            $route = TransportRoute::with(['branch', 'vehicle', 'driver', 'students', 'creator'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $route
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Route not found'
            ], 404);
        }
    }

    /**
     * Update route
     */
    public function update(Request $request, string $id)
    {
        DB::beginTransaction();
        try {
            $route = TransportRoute::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'route_name' => 'string|max:255',
                'route_number' => 'string|max:50|unique:transport_routes,route_number,' . $id,
                'start_point' => 'string|max:255',
                'end_point' => 'string|max:255',
                'stops' => 'nullable|json',
                'vehicle_id' => 'nullable|exists:vehicles,id',
                'driver_id' => 'nullable|exists:users,id',
                'start_time' => 'nullable|date_format:H:i',
                'end_time' => 'nullable|date_format:H:i',
                'fare' => 'nullable|numeric|min:0',
                'distance_km' => 'nullable|numeric|min:0',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = [];
            if ($request->has('route_name')) $updateData['route_name'] = strip_tags($request->route_name);
            if ($request->has('route_number')) $updateData['route_number'] = $request->route_number;
            if ($request->has('start_point')) $updateData['start_point'] = strip_tags($request->start_point);
            if ($request->has('end_point')) $updateData['end_point'] = strip_tags($request->end_point);
            if ($request->has('stops')) $updateData['stops'] = $request->stops;
            if ($request->has('vehicle_id')) $updateData['vehicle_id'] = $request->vehicle_id;
            if ($request->has('driver_id')) $updateData['driver_id'] = $request->driver_id;
            if ($request->has('start_time')) $updateData['start_time'] = $request->start_time;
            if ($request->has('end_time')) $updateData['end_time'] = $request->end_time;
            if ($request->has('fare')) $updateData['fare'] = $request->fare;
            if ($request->has('distance_km')) $updateData['distance_km'] = $request->distance_km;
            if ($request->has('is_active')) $updateData['is_active'] = $request->is_active;
            
            $updateData['updated_by'] = $request->user()->id;

            $route->update($updateData);

            DB::commit();

            Log::info('Route updated', ['route_id' => $route->id]);

            return response()->json([
                'success' => true,
                'message' => 'Route updated successfully',
                'data' => $route->load(['branch', 'vehicle', 'driver'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update route error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update route',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Delete route
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {
            $route = TransportRoute::findOrFail($id);
            
            // Check if route has assigned students
            if ($route->students()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete route with assigned students'
                ], 422);
            }

            $route->delete();
            DB::commit();

            Log::info('Route deleted', ['route_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Route deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete route error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete route',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get students assigned to a route
     */
    public function getRouteStudents(string $id)
    {
        try {
            $route = TransportRoute::with(['students' => function($query) {
                $query->select('users.id', 'users.first_name', 'users.last_name', 'users.email', 'users.phone', 'users.avatar');
            }])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'route' => $route,
                    'students' => $route->students,
                    'student_count' => $route->students->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get route students error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch route students',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    // ==================== VEHICLES MANAGEMENT ====================

    /**
     * Get all vehicles
     */
    public function getVehicles(Request $request)
    {
        try {
            $query = Vehicle::with(['branch', 'creator']);

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('vehicle_type')) {
                $query->where('vehicle_type', $request->vehicle_type);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // OPTIMIZED Search filter - prefix search for better index usage
            if ($request->has('search') && !empty($request->search)) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('vehicle_number', 'like', "{$search}%")
                      ->orWhere('vehicle_type', 'like', "{$search}%")
                      ->orWhere('vehicle_model', 'like', "{$search}%");
                });
            }

            // Define sortable columns
            $sortableColumns = [
                'id',
                'vehicle_number',
                'vehicle_type',
                'vehicle_model',
                'capacity',
                'is_active',
                'created_at'
            ];

            // Apply pagination and sorting (default: 25 per page, sorted by vehicle_number asc)
            $vehicles = $this->paginateAndSort($query, $request, $sortableColumns, 'vehicle_number', 'asc');

            return response()->json([
                'success' => true,
                'data' => $vehicles->items(),
                'meta' => [
                    'current_page' => $vehicles->currentPage(),
                    'per_page' => $vehicles->perPage(),
                    'total' => $vehicles->total(),
                    'last_page' => $vehicles->lastPage(),
                    'from' => $vehicles->firstItem(),
                    'to' => $vehicles->lastItem(),
                    'has_more_pages' => $vehicles->hasMorePages()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get vehicles error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch vehicles',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Store new vehicle
     */
    public function storeVehicle(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|exists:branches,id',
                'vehicle_number' => 'required|string|max:50|unique:vehicles',
                'vehicle_type' => 'required|string|in:Bus,Van,Car,Mini Bus',
                'vehicle_model' => 'nullable|string|max:100',
                'capacity' => 'required|integer|min:1',
                'driver_name' => 'nullable|string|max:255',
                'driver_phone' => 'nullable|string|max:20',
                'driver_license' => 'nullable|string|max:50',
                'insurance_expiry' => 'nullable|date',
                'fitness_expiry' => 'nullable|date',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $vehicle = Vehicle::create([
                'branch_id' => $request->branch_id,
                'vehicle_number' => $request->vehicle_number,
                'vehicle_type' => $request->vehicle_type,
                'vehicle_model' => $request->vehicle_model,
                'capacity' => $request->capacity,
                'driver_name' => strip_tags($request->driver_name ?? ''),
                'driver_phone' => $request->driver_phone,
                'driver_license' => $request->driver_license,
                'insurance_expiry' => $request->insurance_expiry,
                'fitness_expiry' => $request->fitness_expiry,
                'is_active' => $request->is_active ?? true,
                'created_by' => $request->user()->id
            ]);

            DB::commit();

            Log::info('Vehicle created', ['vehicle_id' => $vehicle->id]);

            return response()->json([
                'success' => true,
                'message' => 'Vehicle created successfully',
                'data' => $vehicle
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create vehicle error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create vehicle',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}
