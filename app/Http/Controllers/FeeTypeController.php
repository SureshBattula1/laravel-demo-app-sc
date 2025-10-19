<?php

namespace App\Http\Controllers;

use App\Models\FeeType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class FeeTypeController extends Controller
{
    /**
     * Display a listing of fee types
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = FeeType::with('branch');

            // Filter by branch
            if ($request->has('branch_id') && $request->branch_id) {
                $query->where('branch_id', $request->branch_id);
            }

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            }

            // Search by name or code
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filter by mandatory status
            if ($request->has('is_mandatory')) {
                $query->where('is_mandatory', filter_var($request->is_mandatory, FILTER_VALIDATE_BOOLEAN));
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);

            $feeTypes = $query->get();

            return response()->json([
                'success' => true,
                'message' => 'Fee types retrieved successfully',
                'data' => $feeTypes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve fee types: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created fee type
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:fee_types,code',
                'description' => 'nullable|string',
                'branch_id' => 'required|exists:branches,id',
                'is_mandatory' => 'boolean',
                'is_refundable' => 'boolean',
                'is_active' => 'boolean',
            ]);

            $feeType = FeeType::create($validated);
            $feeType->load('branch');

            return response()->json([
                'success' => true,
                'message' => 'Fee type created successfully',
                'data' => $feeType
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create fee type: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified fee type
     */
    public function show(string $id): JsonResponse
    {
        try {
            $feeType = FeeType::with('branch')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Fee type retrieved successfully',
                'data' => $feeType
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fee type not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve fee type: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified fee type
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $feeType = FeeType::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'code' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('fee_types', 'code')->ignore($feeType->id)
                ],
                'description' => 'nullable|string',
                'branch_id' => 'sometimes|required|exists:branches,id',
                'is_mandatory' => 'boolean',
                'is_refundable' => 'boolean',
                'is_active' => 'boolean',
            ]);

            $feeType->update($validated);
            $feeType->load('branch');

            return response()->json([
                'success' => true,
                'message' => 'Fee type updated successfully',
                'data' => $feeType
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fee type not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update fee type: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified fee type
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $feeType = FeeType::findOrFail($id);

            // Check if fee type is being used in fee structures (by name match)
            $structureCount = \App\Models\FeeStructure::where('fee_type', $feeType->name)->count();
            if ($structureCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete fee type. It is being used in {$structureCount} fee structure(s)."
                ], 422);
            }

            $feeType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Fee type deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fee type not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete fee type: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle fee type active status
     */
    public function toggleStatus(string $id): JsonResponse
    {
        try {
            $feeType = FeeType::findOrFail($id);
            $feeType->is_active = !$feeType->is_active;
            $feeType->save();
            $feeType->load('branch');

            return response()->json([
                'success' => true,
                'message' => 'Fee type status updated successfully',
                'data' => $feeType
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fee type not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status: ' . $e->getMessage()
            ], 500);
        }
    }
}

