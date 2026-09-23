<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\CompanyAcademicYearService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompanyPortalSchoolCreateTest extends TestCase
{
    use RefreshDatabase;

    // Requires MySQL. phpunit.xml uses sqlite, and existing migrations include MySQL-only UPDATE JOIN syntax.

    private function seedRoles(): void
    {
        Role::create([
            'name' => 'Super Admin',
            'slug' => 'super-admin',
            'level' => 1,
            'is_system_role' => true,
            'is_active' => true,
        ]);
        Role::create([
            'name' => 'Branch Admin',
            'slug' => 'branch-admin',
            'level' => 2,
            'is_system_role' => true,
            'is_active' => true,
        ]);
    }

    private function actingAsCompanyAdmin(): array
    {
        $company = Company::create([
            'name' => 'EduCorp',
            'code' => 'EDUCO',
            'email' => 'ops@educorp.test',
            'phone' => '+919800000000',
            'status' => 'Active',
        ]);

        $admin = User::factory()->create([
            'email' => 'company.admin@educorp.test',
            'user_type' => 'CompanyAdmin',
            'company_id' => $company->id,
            'role' => 'SuperAdmin',
            'branch_id' => null,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum');

        return [$company, $admin];
    }

    private function validPayload(int $companyId): array
    {
        return [
            'company_id' => $companyId,
            'name' => 'Green Valley School',
            'code' => 'GVS001',
            'status' => 'Active',
            'branch' => [
                'name' => 'Main Campus',
                'code' => 'GVS-MAIN',
                'address' => '8 Hill Road',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'country' => 'India',
                'pincode' => '411001',
                'phone' => '+919800000001',
                'email' => 'office@greenvalley.test',
                'principal_name' => 'Anita Sharma',
                'principal_contact' => '+919800000003',
                'principal_email' => 'principal@greenvalley.test',
                'branch_admin_password' => 'Branch@123',
            ],
            'admin_user' => [
                'first_name' => 'Rahul',
                'last_name' => 'Mehta',
                'email' => 'admin@greenvalley.test',
                'password' => 'Admin@123',
                'phone' => '+919800000002',
            ],
        ];
    }

    public function test_creates_school_with_superadmin_branch_admin_year_and_grades(): void
    {
        $this->seedRoles();
        [$company] = $this->actingAsCompanyAdmin();

        $response = $this->postJson('/api/company-portal/schools', $this->validPayload((int) $company->id));

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('admin_user.email', 'admin@greenvalley.test')
            ->assertJsonPath('admin_user.role', 'SuperAdmin')
            ->assertJsonPath('branch_admin.email', 'principal@greenvalley.test')
            ->assertJsonPath('branch_admin.role', 'BranchAdmin');

        $this->assertDatabaseHas('schools', [
            'name' => 'Green Valley School',
            'code' => 'GVS001',
            'company_id' => $company->id,
        ]);

        $this->assertDatabaseHas('branches', [
            'code' => 'GVS-MAIN',
            'is_main_branch' => 1,
            'principal_email' => 'principal@greenvalley.test',
        ]);

        $superAdmin = User::where('email', 'admin@greenvalley.test')->first();
        $branchAdmin = User::where('email', 'principal@greenvalley.test')->first();
        $this->assertNotNull($superAdmin);
        $this->assertNotNull($branchAdmin);
        $this->assertSame('SuperAdmin', $superAdmin->role);
        $this->assertSame('BranchAdmin', $branchAdmin->role);
        $this->assertTrue(Hash::check('Admin@123', $superAdmin->password));
        $this->assertTrue(Hash::check('Branch@123', $branchAdmin->password));
        $this->assertSame($superAdmin->branch_id, $branchAdmin->branch_id);

        [$yearName] = app(CompanyAcademicYearService::class)->indianFinancialYearWindow();
        $this->assertDatabaseHas('academic_years', [
            'company_id' => $company->id,
            'name' => $yearName,
            'is_current' => 1,
            'is_active' => 1,
        ]);

        $schoolId = $response->json('data.id');
        $this->assertGreaterThanOrEqual(12, DB::table('grades')->where('school_id', $schoolId)->whereNull('branch_id')->count());
        $this->assertGreaterThanOrEqual(12, DB::table('grades')->where('branch_id', $superAdmin->branch_id)->count());
        $this->assertGreaterThanOrEqual(12, DB::table('classes')->where('branch_id', $superAdmin->branch_id)->where('academic_year', $yearName)->count());
    }

    public function test_rejects_matching_superadmin_and_branch_admin_emails(): void
    {
        $this->seedRoles();
        [$company] = $this->actingAsCompanyAdmin();

        $payload = $this->validPayload((int) $company->id);
        $payload['branch']['principal_email'] = $payload['admin_user']['email'];

        $response = $this->postJson('/api/company-portal/schools', $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertArrayHasKey('branch.principal_email', $response->json('errors'));
    }

    public function test_reuses_existing_current_academic_year(): void
    {
        $this->seedRoles();
        [$company] = $this->actingAsCompanyAdmin();

        AcademicYear::create([
            'company_id' => $company->id,
            'name' => '2024-2025',
            'start_date' => '2024-04-01',
            'end_date' => '2025-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/company-portal/schools', $this->validPayload((int) $company->id));
        $response->assertStatus(201);

        $this->assertSame(1, AcademicYear::query()->where('company_id', $company->id)->count());
        $this->assertDatabaseHas('academic_years', [
            'company_id' => $company->id,
            'name' => '2024-2025',
            'is_current' => 1,
        ]);
    }
}
