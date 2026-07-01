<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression gate for WS2 tenant scoping (BelongsToTenant global scope +
 * scopedToTenant macro). Proves cross-branch reads are blocked on the
 * model-centric paths that were leaking (find/show/get), without relying on
 * the (currently broken) model factories — fixtures are inserted directly.
 */
class TenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private int $branchA;
    private int $branchB;
    private int $studentA;
    private int $studentB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTwoBranches();
    }

    public function test_branch_admin_sees_only_own_branch_students(): void
    {
        $this->actingAs($this->branchAdmin($this->branchA));

        $ids = Student::query()->pluck('id')->all();

        $this->assertContains($this->studentA, $ids);
        $this->assertNotContains($this->studentB, $ids, 'Branch A admin must NOT see branch B students');
    }

    public function test_branch_admin_cannot_find_other_branch_student(): void
    {
        $this->actingAs($this->branchAdmin($this->branchA));

        // The single-record leak class: find() on another branch's row must be null.
        $this->assertNull(Student::find($this->studentB));
        $this->assertNotNull(Student::find($this->studentA));
    }

    public function test_superadmin_sees_all_students(): void
    {
        $this->actingAs($this->superAdmin());

        $ids = Student::query()->pluck('id')->all();

        $this->assertContains($this->studentA, $ids);
        $this->assertContains($this->studentB, $ids);
    }

    public function test_query_builder_macro_scopes_raw_queries(): void
    {
        $this->actingAs($this->branchAdmin($this->branchA));

        $rows = DB::table('students')->scopedToTenant('students.branch_id')->pluck('id')->all();

        $this->assertContains($this->studentA, $rows);
        $this->assertNotContains($this->studentB, $rows);
    }

    public function test_trait_generalizes_to_other_models(): void
    {
        // Prove the trait works beyond the Student pilot, using Teacher.
        $tA = $this->makeTeacher($this->branchA, 'A');
        $tB = $this->makeTeacher($this->branchB, 'B');

        $this->actingAs($this->branchAdmin($this->branchA));

        $ids = Teacher::query()->pluck('id')->all();
        $this->assertContains($tA, $ids);
        $this->assertNotContains($tB, $ids, 'Branch A admin must NOT see branch B teachers');
        $this->assertNull(Teacher::find($tB));
    }

    public function test_without_tenant_scope_escape_hatch_returns_all(): void
    {
        $this->actingAs($this->branchAdmin($this->branchA));

        $ids = Student::withoutTenantScope()->pluck('id')->all();

        $this->assertContains($this->studentA, $ids);
        $this->assertContains($this->studentB, $ids);
    }

    // ---- fixtures ----------------------------------------------------------

    private function seedTwoBranches(): void
    {
        $companyId = DB::table('companies')->insertGetId([
            'name' => 'Test Co', 'code' => 'TCO', 'email' => 'co@test.com', 'phone' => '0000000000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $schoolId = DB::table('schools')->insertGetId([
            'company_id' => $companyId, 'name' => 'Test School', 'code' => 'TS',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->branchA = $this->makeBranch($schoolId, 'Branch A', 'BR-A');
        $this->branchB = $this->makeBranch($schoolId, 'Branch B', 'BR-B');
        $this->studentA = $this->makeStudent($this->branchA, 'A');
        $this->studentB = $this->makeStudent($this->branchB, 'B');
    }

    private function makeBranch(int $schoolId, string $name, string $code): int
    {
        return DB::table('branches')->insertGetId([
            'school_id' => $schoolId, 'name' => $name, 'code' => $code,
            'address' => 'addr', 'city' => 'city', 'state' => 'state', 'country' => 'IN',
            'pincode' => '000000', 'phone' => '0000000000', 'email' => strtolower($code) . '@test.com',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeStudent(int $branchId, string $tag): int
    {
        $userId = DB::table('users')->insertGetId([
            'first_name' => "Stu$tag", 'last_name' => 'Test', 'email' => "stu$tag@test.com",
            'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('students')->insertGetId([
            'user_id' => $userId, 'branch_id' => $branchId,
            'admission_number' => "ADM-$tag", 'admission_date' => '2024-01-01',
            'grade' => '1', 'academic_year' => '2024-2025', 'date_of_birth' => '2010-01-01',
            'gender' => 'Male', 'current_address' => 'addr', 'city' => 'city', 'state' => 'state',
            'pincode' => '000000', 'father_name' => 'F', 'father_phone' => '0000000000',
            'mother_name' => 'M', 'emergency_contact_name' => 'E', 'emergency_contact_phone' => '0000000000',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function makeTeacher(int $branchId, string $tag): int
    {
        $userId = DB::table('users')->insertGetId([
            'first_name' => "Tch$tag", 'last_name' => 'Test', 'email' => "tch$tag@test.com",
            'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        return DB::table('teachers')->insertGetId([
            'user_id' => $userId, 'branch_id' => $branchId, 'employee_id' => "EMP-$tag",
            'designation' => 'Teacher', 'date_of_birth' => '1990-01-01', 'gender' => 'Male',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function branchAdmin(int $branchId): User
    {
        $id = DB::table('users')->insertGetId([
            'first_name' => 'Branch', 'last_name' => 'Admin', 'email' => "ba$branchId@test.com",
            'password' => bcrypt('x'), 'role' => 'BranchAdmin', 'branch_id' => $branchId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return User::withoutGlobalScopes()->findOrFail($id);
    }

    private function superAdmin(): User
    {
        $id = DB::table('users')->insertGetId([
            'first_name' => 'Super', 'last_name' => 'Admin', 'email' => 'sa@test.com',
            'password' => bcrypt('x'), 'role' => 'SuperAdmin',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return User::withoutGlobalScopes()->findOrFail($id);
    }
}
