<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

/**
 * Backfills the `students` and `teachers` domain tables (and `student_enrollments`)
 * for User accounts that were created by MultiSchoolSystemSeeder as login accounts only.
 *
 * Idempotent: re-running skips users that already have a matching domain row.
 */
class BackfillDomainRecordsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        $sections = ['A', 'B', 'C'];

        // 1) Ensure a current academic year exists (the system currently has none).
        $ay = DB::table('academic_years')->where('is_current', 1)->first()
            ?? DB::table('academic_years')->orderBy('id')->first();

        if (!$ay) {
            $ayId = DB::table('academic_years')->insertGetId([
                'name'        => '2025-2026',
                'start_date'  => '2025-06-01',
                'end_date'    => '2026-04-30',
                'is_current'  => 1,
                'is_active'   => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $ayName = '2025-2026';
        } else {
            $ayId   = $ay->id;
            $ayName = $ay->name;
        }

        $creatorId = DB::table('users')->where('role', 'SuperAdmin')->orderBy('id')->value('id');

        // 2) Backfill STUDENTS (+ enrollments)
        $studentUsers = DB::table('users')
            ->where('role', 'Student')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        $studentsCreated = 0;
        $enrollCreated   = 0;
        $perBranch       = [];

        foreach ($studentUsers as $u) {
            $branch = DB::table('branches')->where('id', $u->branch_id)->first();
            if (!$branch) {
                continue;
            }

            $perBranch[$u->branch_id] = ($perBranch[$u->branch_id] ?? 0) + 1;
            $n       = $perBranch[$u->branch_id];
            $grade   = (string) ((($n - 1) % 12) + 1);   // grades 1..12
            $section = $sections[($n - 1) % count($sections)];
            $phone   = '90000' . str_pad((string) $u->id, 5, '0', STR_PAD_LEFT);

            $existing = DB::table('students')->where('user_id', $u->id)->first();
            if ($existing) {
                $studentId = $existing->id;
            } else {
                $studentId = DB::table('students')->insertGetId([
                    'user_id'                 => $u->id,
                    'branch_id'               => $u->branch_id,
                    'school_id'               => $branch->school_id,
                    'academic_year_id'        => $ayId,
                    'academic_year'           => $ayName,
                    'admission_number'        => 'ADM-' . $u->branch_id . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                    'admission_date'          => '2025-06-01',
                    'admission_type'          => 'Regular',
                    'roll_number'             => (string) $n,
                    'grade'                   => $grade,
                    'section'                 => $section,
                    'date_of_birth'           => Carbon::parse('2012-01-01')->addDays($n * 9)->toDateString(),
                    'gender'                  => ($n % 2 === 0) ? 'Female' : 'Male',
                    'nationality'             => 'Indian',
                    'current_address'         => ($branch->city ?? 'NA') . ', ' . ($branch->state ?? 'NA'),
                    'city'                    => $branch->city ?? 'NA',
                    'state'                   => $branch->state ?? 'NA',
                    'country'                 => 'India',
                    'pincode'                 => $branch->pincode ?? '500000',
                    'father_name'             => $u->first_name . ' (Father)',
                    'father_phone'            => $phone,
                    'mother_name'             => $u->first_name . ' (Mother)',
                    'emergency_contact_name'  => $u->first_name . ' (Guardian)',
                    'emergency_contact_phone' => $phone,
                    'medium_of_instruction'   => 'English',
                    'student_status'          => 'Active',
                    'admission_status'        => 'Admitted',
                    'created_at'              => $now,
                    'updated_at'              => $now,
                ]);
                $studentsCreated++;
            }

            // Dependent table: student_enrollments for the current academic year
            if (Schema::hasTable('student_enrollments')) {
                $hasEnroll = DB::table('student_enrollments')
                    ->where('student_id', $studentId)
                    ->where('academic_year_id', $ayId)
                    ->exists();

                if (!$hasEnroll) {
                    DB::table('student_enrollments')->insert([
                        'student_id'       => $studentId,
                        'school_id'        => $branch->school_id,
                        'branch_id'        => $u->branch_id,
                        'academic_year_id' => $ayId,
                        'grade'            => $grade,
                        'section'          => $section,
                        'roll_number'      => (string) $n,
                        'status'           => 'Active',
                        'created_by'       => $creatorId,
                        'updated_by'       => $creatorId,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                    $enrollCreated++;
                }
            }
        }

        // 3) Backfill TEACHERS
        $teacherUsers = DB::table('users')
            ->where('role', 'Teacher')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get();

        $teachersCreated = 0;
        $tPerBranch      = [];

        foreach ($teacherUsers as $u) {
            $branch = DB::table('branches')->where('id', $u->branch_id)->first();
            if (!$branch) {
                continue;
            }

            if (DB::table('teachers')->where('user_id', $u->id)->exists()) {
                continue;
            }

            $tPerBranch[$u->branch_id] = ($tPerBranch[$u->branch_id] ?? 0) + 1;
            $n     = $tPerBranch[$u->branch_id];
            $phone = '80000' . str_pad((string) $u->id, 5, '0', STR_PAD_LEFT);

            DB::table('teachers')->insert([
                'user_id'                 => $u->id,
                'branch_id'               => $u->branch_id,
                'school_id'               => $branch->school_id,
                'employee_id'             => 'EMP-' . $u->branch_id . '-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                'category_type'           => 'Teaching',
                'joining_date'            => '2024-06-01',
                'designation'             => 'Teacher',
                'employee_type'           => 'Permanent',
                'experience_years'        => 5,
                'date_of_birth'           => Carbon::parse('1988-01-01')->addDays($n * 30)->toDateString(),
                'gender'                  => ($n % 2 === 0) ? 'Female' : 'Male',
                'nationality'             => 'Indian',
                'current_address'         => ($branch->city ?? 'NA') . ', ' . ($branch->state ?? 'NA'),
                'city'                    => $branch->city ?? null,
                'state'                   => $branch->state ?? null,
                'emergency_contact_name'  => $u->first_name . ' (Emergency)',
                'emergency_contact_phone' => $phone,
                'teacher_status'          => 'Active',
                'created_at'              => $now,
                'updated_at'              => $now,
            ]);
            $teachersCreated++;
        }

        $this->command->info("✅ Backfill complete.");
        $this->command->info("   Academic Year: {$ayName} (id {$ayId})");
        $this->command->info("   Students created: {$studentsCreated}");
        $this->command->info("   Enrollments created: {$enrollCreated}");
        $this->command->info("   Teachers created: {$teachersCreated}");
    }
}
