<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentOwnRecordAccessTest extends TestCase
{
    private static bool $schemaReady = false;

    protected function setUp(): void
    {
        parent::setUp();

        $path = '/tmp/school-access-tests.sqlite';
        $fresh = ! self::$schemaReady;
        if ($fresh && is_file($path)) {
            unlink($path);
        }
        if (! is_file($path)) {
            touch($path);
        }
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $path,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        if ($fresh) {
            $this->artisan('migrate:fresh', ['--force' => true, '--database' => 'sqlite', '--seed' => false])->run();
            self::$schemaReady = true;
        }
    }

    public function test_student_cannot_promote_or_open_another_student(): void
    {
        $branch = $this->createBranch();
        $student = $this->makeUser('Student', $branch->id, 'own.student@example.com');
        $other = $this->makeUser('Student', $branch->id, 'other.student@example.com');
        $this->makeStudent($student, $branch->id, 'OWN-1');
        $this->makeStudent($other, $branch->id, 'OTHER-1');
        $this->grant($student, 'students.view');

        $this->actingAs($student, 'sanctum');

        $this->postJson('/api/students/promote', [])->assertForbidden();
        $this->getJson('/api/students/by-user/'.$other->id)
            ->assertNotFound()
            ->assertJsonPath('message', 'Student profile not found');
    }

    public function test_branch_admin_with_promote_permission_passes_middleware(): void
    {
        $branch = $this->createBranch();
        $admin = $this->makeUser('BranchAdmin', $branch->id, 'branch.admin@example.com');
        $this->grant($admin, 'students.promote');

        $this->actingAs($admin, 'sanctum');

        $response = $this->postJson('/api/students/promote', []);
        $this->assertNotSame(403, $response->status());
        $response->assertStatus(422);
    }

    public function test_student_list_returns_only_their_own_row(): void
    {
        $branch = $this->createBranch();
        $student = $this->makeUser('Student', $branch->id, 'list.student@example.com');
        $other = $this->makeUser('Student', $branch->id, 'list.other@example.com');
        $this->makeStudent($student, $branch->id, 'LIST-OWN');
        $this->makeStudent($other, $branch->id, 'LIST-OTHER');
        $this->grant($student, 'students.view');

        $this->actingAs($student, 'sanctum');

        $response = $this->getJson('/api/students?all_academic_years=1');
        $response->assertOk();

        $numbers = collect($response->json('data'))->pluck('admission_number')->all();
        $this->assertSame(['LIST-OWN'], $numbers);
    }

    public function test_student_cannot_read_another_students_dues_marks_or_attendance(): void
    {
        $branch = $this->createBranch();
        $student = $this->makeUser('Student', $branch->id, 'dues.student@example.com');
        $other = $this->makeUser('Student', $branch->id, 'dues.other@example.com');
        $otherStudent = $this->makeStudent($other, $branch->id, 'DUES-OTHER');

        $this->actingAs($student, 'sanctum');

        $this->getJson('/api/students/'.$otherStudent->id.'/dues')->assertForbidden();
        $this->getJson('/api/exam-marks/student/'.$other->id)->assertNotFound();
        $this->getJson('/api/attendance/student/'.$other->id)->assertNotFound();
    }

    private function makeUser(string $role, int $branchId, string $email): User
    {
        return User::createExplicit([
            'first_name' => 'Test',
            'last_name' => $role,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => true,
            'branch_id' => $branchId,
        ]);
    }

    private function makeStudent(User $user, int $branchId, string $admissionNumber): Student
    {
        $id = DB::table('students')->insertGetId([
            'user_id' => $user->id,
            'branch_id' => $branchId,
            'admission_number' => $admissionNumber,
            'admission_date' => '2026-04-01',
            'grade' => '2',
            'section' => 'C',
            'academic_year' => '2026-2027',
            'date_of_birth' => '2015-01-01',
            'gender' => 'Male',
            'nationality' => 'Indian',
            'current_address' => '123 Test Street',
            'city' => 'Test City',
            'state' => 'Test State',
            'country' => 'India',
            'pincode' => '123456',
            'father_name' => 'Father',
            'father_phone' => '9999999999',
            'mother_name' => 'Mother',
            'emergency_contact_name' => 'Emergency',
            'emergency_contact_phone' => '9999999998',
            'student_status' => 'Active',
            'admission_status' => 'Confirmed',
            'medium_of_instruction' => 'English',
            'medical_insurance' => 0,
            'vaccination_status' => 'Complete',
            'special_needs' => 0,
            'hostel_required' => 0,
            'admission_type' => 'New',
            'number_of_siblings' => 0,
            'sibling_discount_applicable' => 0,
            'sibling_discount_percentage' => 0,
            'transport_required' => 0,
            'fee_concession_applicable' => 0,
            'concession_percentage' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Student::query()->findOrFail($id);
    }

    private function grant(User $user, string $slug): void
    {
        $moduleId = DB::table('modules')->where('slug', 'students')->value('id');
        if (! $moduleId) {
            $moduleId = DB::table('modules')->insertGetId([
                'name' => 'Students',
                'slug' => 'students',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $permissionId = DB::table('permissions')->where('slug', $slug)->value('id');
        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'module_id' => $moduleId,
                'name' => $slug,
                'slug' => $slug,
                'action' => str_contains($slug, '.') ? explode('.', $slug, 2)[1] : 'view',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $roleId = DB::table('roles')->insertGetId([
            'name' => $user->role.' '.$user->id,
            'slug' => strtolower($user->role).'-'.$user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'is_primary' => true,
            'branch_id' => $user->branch_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
