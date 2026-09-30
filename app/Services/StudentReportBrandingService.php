<?php

namespace App\Services;

use App\Models\Student;
use App\Services\Concerns\BuildsStudentReportBranding;

class StudentReportBrandingService
{
    use BuildsStudentReportBranding;

    /**
     * @return array<string, mixed>
     */
    public function forStudent(Student $student, string $reportTitle): array
    {
        return $this->buildReportBrandingContext($student, $reportTitle);
    }
}
