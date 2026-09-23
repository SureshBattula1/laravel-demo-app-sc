<?php

namespace Database\Seeders\Concerns;

use App\Models\Branch;
use App\Models\Company;
use App\Models\School;
use App\Models\User;
use App\Services\CompanyAcademicYearService;
use Illuminate\Support\Facades\DB;

trait ResolvesDemoSchoolContext
{
    public const DEMO_SCHOOL_CODE = 'GV-DEMO';

    public const DEMO_BRANCH_CODE = 'GV-DEMO-MAIN';

    /**
     * @return object{company: Company, school: School, branch: Branch, ay: \App\Models\AcademicYear, branchAdmin: ?User, superAdmin: ?User}|null
     */
    protected function resolveDemoSchoolContext(): ?object
    {
        $school = School::query()->where('code', self::DEMO_SCHOOL_CODE)->first();
        $branch = Branch::query()->where('code', self::DEMO_BRANCH_CODE)->first();

        if (! $school || ! $branch) {
            $this->command?->error('Demo school not found. Run DemoOneSchoolSeeder first.');

            return null;
        }

        $company = Company::query()->find($school->company_id);
        if (! $company) {
            $this->command?->error('Demo school company not found.');

            return null;
        }

        $ay = app(CompanyAcademicYearService::class)->ensureCurrentIndianYear((int) $company->id);

        return (object) [
            'company' => $company,
            'school' => $school,
            'branch' => $branch,
            'ay' => $ay,
            'branchAdmin' => User::query()->where('email', 'branchadmin@greenvalley.demo')->first(),
            'superAdmin' => User::query()->where('email', 'superadmin@greenvalley.demo')->first(),
        ];
    }

    protected function demoTeacherUserId(int $seq): ?int
    {
        return User::query()
            ->where('email', sprintf('teacher%02d@greenvalley.demo', $seq))
            ->value('id');
    }

    protected function demoStudentUserId(string $grade, string $section, int $seq): ?int
    {
        return User::query()
            ->where('email', sprintf('student.g%s%s.%02d@greenvalley.demo', $grade, strtolower($section), $seq))
            ->value('id');
    }

    protected function demoDepartmentId(int $branchId, string $name): ?int
    {
        return DB::table('departments')
            ->where('branch_id', $branchId)
            ->where('name', $name)
            ->whereNull('deleted_at')
            ->value('id');
    }

    protected function demoSectionId(int $branchId, string $grade, string $section): ?int
    {
        return DB::table('sections')
            ->where('branch_id', $branchId)
            ->where('grade_level', $grade)
            ->where('name', $section)
            ->whereNull('deleted_at')
            ->value('id');
    }
}
