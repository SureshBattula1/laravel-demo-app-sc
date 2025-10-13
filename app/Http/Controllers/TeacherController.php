<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TeacherController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = User::with('branch')->where('role', 'Teacher');

            if ($request->branch_id) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->search) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('first_name', 'like', '%' . $search . '%')
                      ->orWhere('last_name', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%');
                });
            }

            $teachers = $query->orderBy('first_name', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => $teachers,
                'count' => $teachers->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get teachers error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch teachers',
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
                    'message' => 'Invalid teacher ID'
                ], 400);
            }

            $teacher = User::with('branch')->where('role', 'Teacher')->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $teacher
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get teacher error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch teacher'
            ], 500);
        }
    }
}

