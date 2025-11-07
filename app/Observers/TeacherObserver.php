<?php

namespace App\Observers;

use App\Models\Teacher;
use App\Models\Role;
use Illuminate\Support\Facades\Log;

class TeacherObserver
{
    /**
     * Handle the Teacher "created" event.
     * Automatically assigns Teacher role when a teacher is created.
     */
    public function created(Teacher $teacher): void
    {
        try {
            // Get the user associated with this teacher
            $user = $teacher->user;
            
            if (!$user) {
                Log::warning('TeacherObserver: No user found for teacher', ['teacher_id' => $teacher->id]);
                return;
            }
            
            // Get teacher role
            $teacherRole = Role::where('slug', 'teacher')->first();
            
            if (!$teacherRole) {
                Log::error('TeacherObserver: Teacher role not found in database');
                return;
            }
            
            // Check if role is already assigned
            $hasRole = $user->roles()->where('role_id', $teacherRole->id)->exists();
            
            if ($hasRole) {
                Log::info('TeacherObserver: Teacher role already assigned', [
                    'user_id' => $user->id,
                    'teacher_id' => $teacher->id
                ]);
                return;
            }
            
            // Assign teacher role
            $user->roles()->attach($teacherRole->id, [
                'is_primary' => true,
                'branch_id' => $teacher->branch_id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            Log::info('TeacherObserver: Teacher role auto-assigned', [
                'user_id' => $user->id,
                'teacher_id' => $teacher->id,
                'branch_id' => $teacher->branch_id
            ]);
            
        } catch (\Exception $e) {
            Log::error('TeacherObserver: Error auto-assigning teacher role', [
                'teacher_id' => $teacher->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}

