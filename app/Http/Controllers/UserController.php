<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Get all users with pagination
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');
        $role = $request->get('role');
        $branch = $request->get('branch_id');

        $query = User::with(['branch']);

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply role filter
        if ($role) {
            $query->where('role', $role);
        }

        // Apply branch filter
        if ($branch) {
            $query->where('branch_id', $branch);
        }

        $users = $query->latest()->paginate($perPage);

        // Add role_id to each user
        foreach ($users->items() as $user) {
            if ($user->role) {
                $roleRecord = DB::table('roles')->where('name', $user->role)->first();
                $user->role_id = $roleRecord ? $roleRecord->id : null;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users
        ]);
    }

    /**
     * Get all users without pagination
     */
    public function all()
    {
        $users = User::with(['branch'])->get();

        // Add role_id to each user
        foreach ($users as $user) {
            if ($user->role) {
                $roleRecord = DB::table('roles')->where('name', $user->role)->first();
                $user->role_id = $roleRecord ? $roleRecord->id : null;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users
        ]);
    }

    /**
     * Get single user
     */
    public function show($id)
    {
        $user = User::with(['branch'])->findOrFail($id);

        // Add role_id to response
        if ($user->role) {
            $roleRecord = DB::table('roles')->where('name', $user->role)->first();
            $user->role_id = $roleRecord ? $roleRecord->id : null;
        }

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => $user
        ]);
    }

    /**
     * Create new user
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'branch_id' => 'nullable|exists:branches,id',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get role name from role_id
        $role = DB::table('roles')->where('id', $request->role_id)->first();
        $roleName = $role ? $role->name : 'Student';

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $roleName,
            'branch_id' => $request->branch_id,
            'is_active' => $request->get('is_active', true),
            'email_verified_at' => now()
        ]);

        // Add role_id to response for frontend
        $userData = $user->toArray();
        $userData['role_id'] = $request->role_id;

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $userData
        ], 201);
    }

    /**
     * Update user
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($id)],
            'password' => 'sometimes|string|min:8|confirmed',
            'role_id' => 'sometimes|exists:roles,id',
            'branch_id' => 'nullable|exists:branches,id',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['first_name', 'last_name', 'email', 'branch_id', 'is_active']);

        // Convert role_id to role name
        if ($request->has('role_id')) {
            $role = DB::table('roles')->where('id', $request->role_id)->first();
            $data['role'] = $role ? $role->name : $user->role;
        }

        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        $userData = $user->fresh()->toArray();
        // Add role_id to response
        if ($request->has('role_id')) {
            $userData['role_id'] = $request->role_id;
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $userData
        ]);
    }

    /**
     * Delete user
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Toggle user active status
     */
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);

        // Prevent toggling your own status
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot toggle your own status'
            ], 403);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($id);
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully'
        ]);
    }
}

