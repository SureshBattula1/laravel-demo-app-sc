<?php

namespace App\Services\Concerns;

use App\Models\Student;
use Illuminate\Support\Facades\Storage;

trait BuildsStudentReportBranding
{
    /**
     * Shared school / student / report header context for branded PDFs.
     *
     * @return array<string, mixed>
     */
    protected function buildReportBrandingContext(Student $student, string $reportTitle): array
    {
        $user = $student->user;
        $branch = $student->branch;
        $school = $branch?->school;

        $schoolDisplayName = $school?->name ?? $branch?->name ?? config('app.name', 'School');
        $branchLine = ($branch?->name && $school?->name && $branch->name !== $school->name)
            ? $branch->name
            : null;

        $settings = is_array($branch?->settings) ? $branch->settings : [];
        $tagline = $settings['tagline'] ?? $settings['motto'] ?? 'Learn • Grow • Achieve';

        $addressLines = array_values(array_filter([
            $branch?->address,
            trim(implode(', ', array_filter([
                $branch?->city,
                $branch?->state,
            ]))),
            trim(implode(' - ', array_filter([
                $branch?->country ?: 'India',
                $branch?->pincode,
            ]))),
        ]));

        $studentName = trim(($user?->first_name ?? '').' '.($user?->last_name ?? ''));
        if ($studentName === '') {
            $studentName = 'Student';
        }

        $grade = $student->grade ?: '—';
        $section = $student->section ?: '—';
        $classLabel = 'Grade '.$grade.' - '.$section;

        $admission = $student->admission_number ?: '—';

        $academicYear = $student->academic_year
            ?? $branch?->current_academic_year
            ?? '—';

        $academicYearDisplay = ($academicYear === '' || $academicYear === '—') ? '—' : $academicYear;
        $academicYearReportLabel = $this->formatAcademicYearLabel($academicYear);

        $photoPath = $student->getAttributes()['profile_picture'] ?? null;
        if (! $photoPath && $user) {
            $photoPath = $user->getAttributes()['avatar'] ?? null;
        }
        $photoPath = $this->normalizePhotoStoragePath($photoPath);

        return [
            'school' => [
                'name' => strtoupper($schoolDisplayName),
                'branch_line' => $branchLine,
                'tagline' => $tagline,
                'address_lines' => $addressLines,
                'logo_data_uri' => $this->resolveLogoDataUri($branch?->logo),
            ],
            'student' => [
                'name' => $studentName,
                'photo_data_uri' => $this->resolveLogoDataUri($photoPath),
                'class' => $classLabel,
                'admission_number' => $admission,
                'academic_year' => $academicYearDisplay,
            ],
            'report' => [
                'title' => $reportTitle,
                'academic_year' => $academicYearReportLabel,
            ],
            'signatures' => [
                'class_teacher' => $settings['class_teacher_label'] ?? 'Class Teacher',
                'principal' => $branch?->principal_name ?: 'Principal',
            ],
        ];
    }

    protected function formatAcademicYearLabel(string $year): string
    {
        $year = trim($year);
        if ($year === '' || $year === '—') {
            return '—';
        }
        if (stripos($year, 'academic') !== false) {
            return $year;
        }

        return 'ACADEMIC YEAR '.strtoupper($year);
    }

    protected function normalizePhotoStoragePath(?string $value): ?string
    {
        if (! $value || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $path = parse_url($value, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                if (str_starts_with($path, '/storage/')) {
                    return ltrim(substr($path, strlen('/storage/')), '/');
                }

                return ltrim($path, '/');
            }
        }

        if (str_starts_with($value, 'storage/')) {
            return substr($value, strlen('storage/'));
        }

        return ltrim($value, '/');
    }

    protected function resolveLogoDataUri(?string $logo): ?string
    {
        if (! $logo || trim($logo) === '') {
            return null;
        }

        $path = $this->normalizePhotoStoragePath($logo) ?? $logo;
        $candidates = [
            $path,
            ltrim($path, '/'),
            'public/'.ltrim($path, '/'),
        ];

        foreach (['public', 'local'] as $disk) {
            foreach ($candidates as $candidate) {
                if (Storage::disk($disk)->exists($candidate)) {
                    $contents = Storage::disk($disk)->get($candidate);
                    $mime = Storage::disk($disk)->mimeType($candidate) ?: 'image/png';

                    return 'data:'.$mime.';base64,'.base64_encode($contents);
                }
            }
        }

        $absolute = public_path(ltrim($path, '/'));
        if (is_file($absolute)) {
            $mime = mime_content_type($absolute) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($absolute));
        }

        return null;
    }

    protected function moneyLabel(float $amount): string
    {
        return '₹'.number_format($amount, 2, '.', ',');
    }
}
