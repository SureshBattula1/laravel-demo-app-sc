<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Branch::query();

            if ($request->has('is_active')) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->search) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('code', 'like', '%' . $search . '%')
                      ->orWhere('city', 'like', '%' . $search . '%');
                });
            }

            $branches = $query->orderBy('name', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => $branches,
                'count' => $branches->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get branches error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branches',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|regex:/^[a-zA-Z0-9\s\-&]+$/',
                'code' => 'required|string|max:50|unique:branches|regex:/^[A-Z0-9\-]+$/',
                'address' => 'required|string|max:500',
                'city' => 'required|string|max:100|regex:/^[a-zA-Z\s]+$/',
                'state' => 'required|string|max:100|regex:/^[a-zA-Z\s]+$/',
                'country' => 'required|string|max:100|regex:/^[a-zA-Z\s]+$/',
                'pincode' => 'required|string|max:10|regex:/^[0-9]+$/',
                'phone' => 'required|string|max:20|unique:branches|regex:/^[0-9+\-\s()]+$/',
                'email' => 'required|email|max:255|unique:branches',
                'principal_name' => 'nullable|string|max:255|regex:/^[a-zA-Z\s]+$/',
                'principal_contact' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
                'principal_email' => 'nullable|email|max:255',
                'established_date' => 'nullable|date|before_or_equal:today',
                'affiliation_number' => 'nullable|string|max:100',
                'is_main_branch' => 'boolean',
                'is_active' => 'boolean',
                'settings' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $sanitizedData = [
                'name' => strip_tags($request->name),
                'code' => strtoupper(strip_tags($request->code)),
                'address' => strip_tags($request->address),
                'city' => strip_tags($request->city),
                'state' => strip_tags($request->state),
                'country' => strip_tags($request->country),
                'pincode' => preg_replace('/[^0-9]/', '', $request->pincode),
                'phone' => preg_replace('/[^0-9+\-\s()]/', '', $request->phone),
                'email' => filter_var($request->email, FILTER_SANITIZE_EMAIL),
                'principal_name' => strip_tags($request->principal_name),
                'principal_contact' => preg_replace('/[^0-9+\-\s()]/', '', $request->principal_contact),
                'principal_email' => filter_var($request->principal_email, FILTER_SANITIZE_EMAIL),
                'established_date' => $request->established_date,
                'affiliation_number' => strip_tags($request->affiliation_number),
                'is_main_branch' => $request->is_main_branch ?? false,
                'is_active' => $request->is_active ?? true,
                'settings' => $request->settings
            ];

            $branch = Branch::create($sanitizedData);

            DB::commit();

            Log::info('Branch created', ['branch_id' => $branch->id]);

            return response()->json([
                'success' => true,
                'message' => 'Branch created successfully',
                'data' => $branch
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create branch error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create branch',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid branch ID'
                ], 400);
            }

            $branch = Branch::with(['departments', 'users'])->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $branch
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get branch error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branch',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid branch ID'
                ], 400);
            }

            $branch = Branch::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255|regex:/^[a-zA-Z0-9\s\-&]+$/',
                'code' => 'sometimes|string|max:50|unique:branches,code,' . $id . '|regex:/^[A-Z0-9\-]+$/',
                'address' => 'sometimes|string|max:500',
                'city' => 'sometimes|string|max:100|regex:/^[a-zA-Z\s]+$/',
                'state' => 'sometimes|string|max:100|regex:/^[a-zA-Z\s]+$/',
                'country' => 'sometimes|string|max:100|regex:/^[a-zA-Z\s]+$/',
                'pincode' => 'sometimes|string|max:10|regex:/^[0-9]+$/',
                'phone' => 'sometimes|string|max:20|unique:branches,phone,' . $id . '|regex:/^[0-9+\-\s()]+$/',
                'email' => 'sometimes|email|max:255|unique:branches,email,' . $id,
                'principal_name' => 'nullable|string|max:255|regex:/^[a-zA-Z\s]+$/',
                'principal_contact' => 'nullable|string|max:20|regex:/^[0-9+\-\s()]+$/',
                'principal_email' => 'nullable|email|max:255',
                'established_date' => 'nullable|date|before_or_equal:today',
                'is_main_branch' => 'sometimes|boolean',
                'is_active' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $updateData = [];
            foreach (['name', 'address', 'city', 'state', 'country', 'principal_name'] as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = strip_tags($request->$field);
                }
            }
            if ($request->has('code')) {
                $updateData['code'] = strtoupper(strip_tags($request->code));
            }
            if ($request->has('email')) {
                $updateData['email'] = filter_var($request->email, FILTER_SANITIZE_EMAIL);
            }
            if ($request->has('phone')) {
                $updateData['phone'] = preg_replace('/[^0-9+\-\s()]/', '', $request->phone);
            }

            $updateData = array_merge($updateData, $request->only([
                'pincode', 'principal_contact', 'principal_email', 'established_date',
                'affiliation_number', 'is_main_branch', 'is_active', 'settings'
            ]));

            $branch->update($updateData);

            DB::commit();

            Log::info('Branch updated', ['branch_id' => $branch->id]);

            return response()->json([
                'success' => true,
                'message' => 'Branch updated successfully',
                'data' => $branch
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update branch error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update branch',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid branch ID'
                ], 400);
            }

            DB::beginTransaction();

            $branch = Branch::findOrFail($id);
            
            // Check if branch has users or departments
            if ($branch->users()->count() > 0 || $branch->departments()->count() > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete branch with existing users or departments'
                ], 400);
            }

            $branch->delete();

            DB::commit();

            Log::info('Branch deleted', ['branch_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Branch deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete branch error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete branch',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function stats($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid branch ID'
                ], 400);
            }

            $branch = Branch::findOrFail($id);
            
            $stats = [
                'total_students' => $branch->students()->count(),
                'total_teachers' => $branch->teachers()->count(),
                'total_departments' => $branch->departments()->count(),
                'active_students' => $branch->students()->where('is_active', true)->count(),
                'active_teachers' => $branch->teachers()->where('is_active', true)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Get branch stats error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branch statistics'
            ], 500);
        }
    }

    public function toggleStatus($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid branch ID'
                ], 400);
            }

            DB::beginTransaction();

            $branch = Branch::findOrFail($id);
            $branch->is_active = !$branch->is_active;
            $branch->save();

            DB::commit();

            Log::info('Branch status toggled', [
                'branch_id' => $id,
                'new_status' => $branch->is_active
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch status updated',
                'data' => ['is_active' => $branch->is_active]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Toggle branch status error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update branch status'
            ], 500);
        }
    }
}
