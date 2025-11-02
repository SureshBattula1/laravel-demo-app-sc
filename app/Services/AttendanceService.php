<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceService
{
    /**
     * Mark bulk attendance with optimization
     */
    public function markBulkAttendance($type, $date, $branchId, $attendanceData, $academicYear = null)
    {
        DB::beginTransaction();
        try {
            $table = $type === 'student' ? 'student_attendance' : 'teacher_attendance';
            $idField = $type === 'student' ? 'student_id' : 'teacher_id';
            $marked = 0;
            $errors = [];

            // Prepare bulk data
            $bulkData = [];
            foreach ($attendanceData as $item) {
                $record = [
                    $idField => $item['id'],
                    'date' => $date,
                    'branch_id' => $branchId,
                    'status' => $item['status'],
                    'remarks' => $item['remarks'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now()
                ];

                if ($type === 'student') {
                    $record['grade_level'] = $item['grade_level'];
                    $record['section'] = $item['section'];
                    $record['academic_year'] = $academicYear ?? date('Y') . '-' . (date('Y') + 1);
                    $record['marked_by'] = auth()->user()->email ?? null;
                }

                $bulkData[] = $record;
            }

            // Use upsert for better performance (Laravel 8+)
            if (method_exists(DB::class, 'upsert')) {
                DB::table($table)->upsert(
                    $bulkData,
                    [$idField, 'date'],
                    ['status', 'remarks', 'updated_at']
                );
                $marked = count($bulkData);
            } else {
                // Fallback for older versions
                foreach ($bulkData as $data) {
                    try {
                        DB::table($table)->updateOrInsert(
                            [$idField => $data[$idField], 'date' => $data['date']],
                            $data
                        );
                        $marked++;
                    } catch (\Exception $e) {
                        $errors[] = "Failed for ID {$data[$idField]}";
                    }
                }
            }

            // Clear cache
            $this->clearAttendanceCache($type, $branchId);

            DB::commit();

            Log::info('Bulk attendance marked', [
                'type' => $type,
                'date' => $date,
                'marked' => $marked
            ]);

            return ['marked' => $marked, 'errors' => $errors];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk attendance failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get attendance with caching
     */
    public function getAttendance($type, $filters = [])
    {
        $cacheKey = "attendance:{$type}:" . md5(json_encode($filters));
        
        return Cache::remember($cacheKey, 180, function () use ($type, $filters) {
            $table = $type === 'student' ? 'student_attendance' : 'teacher_attendance';
            $typeTable = $type === 'student' ? 'students' : 'teachers';
            $idField = $type === 'student' ? 'student_id' : 'teacher_id';

            $query = DB::table($table)
                ->join($typeTable, "{$table}.{$idField}", '=', "{$typeTable}.user_id")
                ->join('users', "{$typeTable}.user_id", '=', 'users.id')
                ->select("{$table}.*", 'users.first_name', 'users.last_name', 'users.email');

            if ($type === 'student') {
                $query->addSelect('students.admission_number', 'students.grade', 'students.section');
            } else {
                $query->addSelect('teachers.employee_id');
            }

            $this->applyAttendanceFilters($query, $filters, $table, $type);

            return $query->orderBy("{$table}.date", 'desc')->get();
        });
    }

    /**
     * Apply attendance filters
     */
    private function applyAttendanceFilters($query, $filters, $table, $type)
    {
        if (!empty($filters['branch_id'])) {
            $query->where("{$table}.branch_id", $filters['branch_id']);
        }

        if (!empty($filters['date'])) {
            $query->whereDate("{$table}.date", $filters['date']);
        }

        if (!empty($filters['from_date'])) {
            $query->whereDate("{$table}.date", '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $query->whereDate("{$table}.date", '<=', $filters['to_date']);
        }

        if (!empty($filters['status'])) {
            $query->where("{$table}.status", $filters['status']);
        }

        if ($type === 'student') {
            if (!empty($filters['grade'])) {
                $query->where('students.grade', $filters['grade']);
            }
            if (!empty($filters['section'])) {
                $query->where('students.section', $filters['section']);
            }
        }
    }

    /**
     * Generate attendance report with statistics
     */
    public function generateReport($type, $fromDate, $toDate, $filters = [])
    {
        $table = $type === 'student' ? 'student_attendance' : 'teacher_attendance';

        $query = DB::table($table)
            ->whereBetween('date', [$fromDate, $toDate]);

        if (!empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if ($type === 'student' && !empty($filters['grade'])) {
            $query->where('grade_level', $filters['grade']);
        }

        $records = $query->get();

        return [
            'records' => $records,
            'summary' => [
                'total_records' => $records->count(),
                'present' => $records->where('status', 'Present')->count(),
                'absent' => $records->where('status', 'Absent')->count(),
                'late' => $records->where('status', 'Late')->count(),
                'percentage' => $records->count() > 0
                    ? round(($records->where('status', 'Present')->count() / $records->count()) * 100, 2)
                    : 0
            ]
        ];
    }

    /**
     * Clear attendance cache
     */
    private function clearAttendanceCache($type, $branchId)
    {
        Cache::forget("attendance:{$type}:" . md5(json_encode(['branch_id' => $branchId])));
    }
}

