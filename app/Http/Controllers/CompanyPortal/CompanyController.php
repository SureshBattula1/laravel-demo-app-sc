<?php

namespace App\Http\Controllers\CompanyPortal;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    /**
     * List all companies (for super admin)
     */
    public function index(Request $request)
    {
        try {
            $query = Company::withCount(['schools', 'activeSchools']);

            // Filtering
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $companies = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $companies->items(),
                'meta' => [
                    'current_page' => $companies->currentPage(),
                    'last_page' => $companies->lastPage(),
                    'per_page' => $companies->perPage(),
                    'total' => $companies->total()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Company list error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch companies'
            ], 500);
        }
    }

    /**
     * Get company details
     */
    public function show(Request $request, $id)
    {
        try {
            $company = Company::with(['schools', 'companyAdmins'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $company
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found'
            ], 404);
        }
    }

    /**
     * Create new company
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:companies,code',
                'email' => 'required|email|unique:companies,email',
                'phone' => 'required|string|max:20',
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
                'pincode' => 'nullable|string|max:20',
                'tax_id' => 'nullable|string|max:50',
                'website' => 'nullable|url|max:255',
                'status' => 'sometimes|in:Active,Inactive,Suspended'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $company = Company::create($request->all());

            DB::commit();

            Log::info('Company created', ['company_id' => $company->id, 'created_by' => $request->user()->id]);

            return response()->json([
                'success' => true,
                'message' => 'Company created successfully',
                'data' => $company
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Company creation error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create company'
            ], 500);
        }
    }

    /**
     * Update company
     */
    public function update(Request $request, $id)
    {
        try {
            $company = Company::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'code' => 'sometimes|required|string|max:50|unique:companies,code,' . $id,
                'email' => 'sometimes|required|email|unique:companies,email,' . $id,
                'phone' => 'sometimes|required|string|max:20',
                'address' => 'nullable|string',
                'city' => 'nullable|string|max:100',
                'state' => 'nullable|string|max:100',
                'country' => 'nullable|string|max:100',
                'pincode' => 'nullable|string|max:20',
                'tax_id' => 'nullable|string|max:50',
                'website' => 'nullable|url|max:255',
                'status' => 'sometimes|in:Active,Inactive,Suspended'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $company->update($request->all());

            DB::commit();

            Log::info('Company updated', ['company_id' => $company->id, 'updated_by' => $request->user()->id]);

            return response()->json([
                'success' => true,
                'message' => 'Company updated successfully',
                'data' => $company->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Company update error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update company'
            ], 500);
        }
    }

    /**
     * Delete company
     */
    public function destroy(Request $request, $id)
    {
        try {
            $company = Company::findOrFail($id);

            // Check if company has active schools
            if ($company->activeSchools()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete company with active schools'
                ], 422);
            }

            DB::beginTransaction();

            $company->delete();

            DB::commit();

            Log::info('Company deleted', ['company_id' => $id, 'deleted_by' => $request->user()->id]);

            return response()->json([
                'success' => true,
                'message' => 'Company deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Company deletion error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete company'
            ], 500);
        }
    }
}

