<?php

namespace App\Observers;

use App\Models\Student;
use App\Models\Role;
use Illuminate\Support\Facades\Log;

class StudentObserver
{
    /**
     * Handle the Student "created" event.
     * Automatically assigns Student role when a student is created.
     */
    public function created(Student $student): void
    {
        try {
            // Get the user associated with this student
            $user = $student->user;
            
            if (!$user) {
                Log::warning('StudentObserver: No user found for student', ['student_id' => $student->id]);
                return;
            }
            
            // Check if user already has student role
            $studentRole = Role::where('slug', 'student')->first();
            
            if (!$studentRole) {
                Log::error('StudentObserver: Student role not found in database');
                return;
            }
            
            // Check if role is already assigned
            $hasRole = $user->roles()->where('role_id', $studentRole->id)->exists();
            
            if ($hasRole) {
                Log::info('StudentObserver: Student role already assigned', [
                    'user_id' => $user->id,
                    'student_id' => $student->id
                ]);
                return;
            }
            
            // Assign student role
            $user->roles()->attach($studentRole->id, [
                'is_primary' => true,
                'branch_id' => $student->branch_id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            Log::info('StudentObserver: Student role auto-assigned', [
                'user_id' => $user->id,
                'student_id' => $student->id,
                'branch_id' => $student->branch_id
            ]);
            
        } catch (\Exception $e) {
            Log::error('StudentObserver: Error auto-assigning student role', [
                'student_id' => $student->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}

