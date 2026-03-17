<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AcademicYearController extends Controller
{
    use PaginatesAndSorts;

    /**
     * Get the current (is_current = true) academic year.
     */
    public function current(Request $request)
    {
        try {
            $academicYear = AcademicYear::query()->current()->active()->first();
            if (!$academicYear) {
                return response()->json([
                    'success' => false,
                    'message' => 'No current academic year is set.',
                    'data' => null,
                ], 404);
            }
            return response()->json([
                'success' => true,
                'data' => $academicYear,
            ]);
        } catch (\Exception $e) {
            Log::error('Academic year current error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch current academic year',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $query = AcademicYear::query();

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }
            if ($request->boolean('active')) {
                $query->where('is_active', true);
            }
            if (!$request->boolean('include_past')) {
                // By default exclude past years for dropdowns; use include_past=1 to get all
                $query->where('end_date', '>=', now()->toDateString());
            }
            if ($request->has('search') && $request->search) {
                $search = '%' . $request->search . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', $search)
                        ->orWhere('description', 'like', $search);
                });
            }

            $sortableColumns = ['name', 'start_date', 'end_date', 'is_current', 'is_active', 'created_at'];
            $defaultSort = $request->get('sort_by') ? null : 'start_date';
            $paginated = $this->paginateAndSort($query, $request, $sortableColumns, $defaultSort ?? 'start_date', 'desc');

            return response()->json([
                'success' => true,
                'message' => 'Academic years retrieved successfully',
                'data' => $paginated->items(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                    'from' => $paginated->firstItem(),
                    'to' => $paginated->lastItem(),
                    'has_more_pages' => $paginated->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Academic years index error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch academic years',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $academicYear = AcademicYear::findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => $academicYear,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Academic year not found'], 404);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_current' => 'boolean',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if (!empty($validated['is_current'])) {
            AcademicYear::query()->update(['is_current' => false]);
            \App\Services\AcademicYearContext::clearCurrentCache();
        }
        $academicYear = AcademicYear::create($validated);
        return response()->json([
            'success' => true,
            'message' => 'Academic year created successfully',
            'data' => $academicYear,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $academicYear = AcademicYear::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:50',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'is_current' => 'boolean',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:500',
        ]);

        if (!empty($validated['is_current'])) {
            AcademicYear::where('id', '!=', $id)->update(['is_current' => false]);
            \App\Services\AcademicYearContext::clearCurrentCache();
        }
        $academicYear->update($validated);
        return response()->json([
            'success' => true,
            'message' => 'Academic year updated successfully',
            'data' => $academicYear->fresh(),
        ]);
    }

    public function destroy($id)
    {
        try {
            $academicYear = AcademicYear::findOrFail($id);
            $academicYear->delete();
            return response()->json([
                'success' => true,
                'message' => 'Academic year deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Academic year not found'], 404);
        }
    }
}
