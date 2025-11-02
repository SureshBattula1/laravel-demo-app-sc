<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ClassSectionController extends Controller
{
    /**
     * Get all classes (unique grade-section combinations)
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('students')
                ->select(
                    'grade',
                    'section',
                    'branch_id',
                    'academic_year',
                    DB::raw('COUNT(*) as student_count'),
                    DB::raw("CONCAT(grade, ' ', COALESCE(section, '')) as class_name")
                )
                ->groupBy('branch_id', 'grade', 'section', 'academic_year');

            // Filters
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('grade')) {
                $query->where('grade', $request->grade);
            }

            if ($request->has('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }

            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('grade', 'like', '%' . $search . '%')
                      ->orWhere('section', 'like', '%' . $search . '%');
                });
            }

            $classes = $query->orderBy('grade', 'asc')
                            ->orderBy('section', 'asc')
                            ->get();

            return response()->json([
                'success' => true,
                'data' => $classes,
                'count' => $classes->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get classes error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch classes',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get students in a specific class
     */
    public function getClassStudents(Request $request, $grade, $section = null)
    {
        try {
            $query = DB::table('students')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->where('students.grade', $grade);

            if ($section && $section !== 'null') {
                $query->where('students.section', $section);
            }

            if ($request->has('branch_id')) {
                $query->where('students.branch_id', $request->branch_id);
            }

            $students = $query->select('students.*', 'users.first_name', 'users.last_name', 'users.email')
                             ->get();

            return response()->json([
                'success' => true,
                'data' => $students,
                'count' => $students->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get class students error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch class students'
            ], 500);
        }
    }

    /**
     * Get all unique grades
     */
    public function getGrades(Request $request)
    {
        try {
            $query = DB::table('students')
                ->select('grade', DB::raw('COUNT(*) as count'))
                ->groupBy('grade');

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            $grades = $query->orderBy('grade', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => $grades
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch grades'
            ], 500);
        }
    }

    /**
     * Get all sections for a specific grade
     */
    public function getSections(Request $request, $grade)
    {
        try {
            $query = DB::table('students')
                ->select('section', DB::raw('COUNT(*) as count'))
                ->where('grade', $grade)
                ->whereNotNull('section')
                ->groupBy('section');

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            $sections = $query->orderBy('section', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => $sections
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sections'
            ], 500);
        }
    }
}

