<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StudentService
{
    /**
     * Get students with optimized queries
     */
    public function getStudents($filters = [], $perPage = 10)
    {
        // Build optimized query with single join
        $query = DB::table('students')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->select([
                'students.*',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.phone',
                'users.is_active',
                DB::raw('CONCAT(users.first_name, " ", users.last_name) as full_name')
            ])
            ->where('users.deleted_at', null);

        // Apply filters using indexes
        $this->applyFilters($query, $filters);

        return $query->orderBy('students.created_at', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Apply filters efficiently
     */
    private function applyFilters($query, $filters)
    {
        if (!empty($filters['grade'])) {
            $query->where('students.grade', $filters['grade']);
        }

        if (!empty($filters['section'])) {
            $query->where('students.section', $filters['section']);
        }

        if (!empty($filters['status'])) {
            $query->where('students.student_status', $filters['status']);
        }

        if (!empty($filters['branch_id'])) {
            $query->where('students.branch_id', $filters['branch_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            // Optimized search using indexed columns
            $query->where(function($q) use ($search) {
                $q->where('users.first_name', 'like', "{$search}%")  // Uses index better
                  ->orWhere('users.last_name', 'like', "{$search}%")
                  ->orWhere('users.email', 'like', "{$search}%")
                  ->orWhere('students.admission_number', 'like', "{$search}%")
                  ->orWhere('students.roll_number', 'like', "{$search}%");
            });
        }
    }

    /**
     * Create student with transaction
     */
    public function createStudent($data)
    {
        DB::beginTransaction();
        try {
            // Create user
            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => 'Student',
                'user_type' => 'Student',
                'branch_id' => $data['branch_id'],
                'is_active' => true
            ]);

            // Create student record
            $studentData = array_merge($data, [
                'user_id' => $user->id,
                'student_status' => 'Active',
                'admission_status' => 'Admitted',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            unset($studentData['first_name'], $studentData['last_name'], $studentData['email'], $studentData['password']);

            $studentId = DB::table('students')->insertGetId($studentData);

            // Link user to student
            $user->update(['user_type_id' => $studentId]);

            DB::commit();

            Log::info('Student created', ['student_id' => $studentId, 'user_id' => $user->id]);

            return [
                'student_id' => $studentId,
                'user_id' => $user->id,
                'admission_number' => $data['admission_number']
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Student creation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Promote students in bulk
     */
    public function promoteStudents($studentIds, $fromGrade, $toGrade, $academicYear)
    {
        DB::beginTransaction();
        try {
            $promoted = DB::table('students')
                ->whereIn('id', $studentIds)
                ->where('grade', $fromGrade)
                ->update([
                    'grade' => $toGrade,
                    'academic_year' => $academicYear,
                    'section' => null,
                    'updated_at' => now()
                ]);

            DB::commit();

            Log::info('Students promoted', [
                'count' => $promoted,
                'from' => $fromGrade,
                'to' => $toGrade
            ]);

            return $promoted;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Student promotion failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get student statistics
     */
    public function getStudentStats($branchId = null)
    {
        $cacheKey = "student:stats:" . ($branchId ?? 'all');
        
        return Cache::remember($cacheKey, 600, function () use ($branchId) {
            $query = DB::table('students');
            
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            return [
                'total' => $query->count(),
                'active' => (clone $query)->where('student_status', 'Active')->count(),
                'by_grade' => (clone $query)->select('grade', DB::raw('COUNT(*) as count'))
                    ->groupBy('grade')
                    ->orderBy('grade')
                    ->get(),
                'by_status' => (clone $query)->select('student_status', DB::raw('COUNT(*) as count'))
                    ->groupBy('student_status')
                    ->get()
            ];
        });
    }
}

