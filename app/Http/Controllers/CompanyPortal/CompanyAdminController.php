<?php

namespace App\Http\Controllers\CompanyPortal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CompanyAdminController extends Controller
{
    /**
     * List company admins for current company
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->user_type !== 'CompanyAdmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $query = User::where('company_id', $user->company_id)
                ->where('user_type', 'CompanyAdmin');

            // Filtering
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $admins = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $admins->items(),
                'meta' => [
                    'current_page' => $admins->currentPage(),
                    'last_page' => $admins->lastPage(),
                    'per_page' => $admins->perPage(),
                    'total' => $admins->total()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Company admin list error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch company admins'
            ], 500);
        }
    }

    /**
     * Create new company admin
     */
    public function store(Request $request)
    {
        try {
            $currentUser = $request->user();
            
            if (!$currentUser || $currentUser->user_type !== 'CompanyAdmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'confirmed',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/'
                ],
            ], [
                'password.regex' => 'Password must contain uppercase, lowercase, number and special character'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $admin = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'company_id' => $currentUser->company_id,
                'user_type' => 'CompanyAdmin',
                'role' => 'CompanyAdmin',
                'is_active' => true
            ]);

            DB::commit();

            Log::info('Company admin created', ['admin_id' => $admin->id, 'created_by' => $currentUser->id]);

            return response()->json([
                'success' => true,
                'message' => 'Company admin created successfully',
                'data' => $admin
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Company admin creation error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create company admin'
            ], 500);
        }
    }

    /**
     * Update company admin
     */
    public function update(Request $request, $id)
    {
        try {
            $currentUser = $request->user();
            
            $admin = User::where('company_id', $currentUser->company_id)
                ->where('user_type', 'CompanyAdmin')
                ->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|required|string|max:255',
                'last_name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $id,
                'phone' => 'sometimes|nullable|string|max:20',
                'is_active' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $admin->update($request->only(['first_name', 'last_name', 'email', 'phone', 'is_active']));

            DB::commit();

            Log::info('Company admin updated', ['admin_id' => $admin->id, 'updated_by' => $currentUser->id]);

            return response()->json([
                'success' => true,
                'message' => 'Company admin updated successfully',
                'data' => $admin->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Company admin update error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update company admin'
            ], 500);
        }
    }

    /**
     * Delete company admin
     */
    public function destroy(Request $request, $id)
    {
        try {
            $currentUser = $request->user();
            
            // Cannot delete yourself
            if ($currentUser->id == $id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete your own account'
                ], 422);
            }

            $admin = User::where('company_id', $currentUser->company_id)
                ->where('user_type', 'CompanyAdmin')
                ->findOrFail($id);

            DB::beginTransaction();

            $admin->delete();

            DB::commit();

            Log::info('Company admin deleted', ['admin_id' => $id, 'deleted_by' => $currentUser->id]);

            return response()->json([
                'success' => true,
                'message' => 'Company admin deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Company admin deletion error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete company admin'
            ], 500);
        }
    }
}

