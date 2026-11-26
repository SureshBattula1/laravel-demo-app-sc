<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\IdCard;
use App\Models\IdCardScan;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\Teacher;
use App\Models\User;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\Facades\DB;

class IdCardService
{
    public function ensureForUser(User $user, ?int $academicYearId, ?int $actorId = null): array
    {
        $facts = $this->factsFor($user, $academicYearId);
        $card = IdCard::query()
            ->where('user_id', $user->id)
            ->where('academic_year_id', $facts['academic_year_id'])
            ->where('status', '!=', 'reissued')
            ->latest('id')
            ->first();

        if (! $card) {
            $card = IdCard::query()->create([
                'user_id' => $user->id,
                'person_type' => $facts['person_type'],
                'branch_id' => $facts['branch_id'],
                'school_id' => $facts['school_id'],
                'academic_year_id' => $facts['academic_year_id'],
                'card_number' => $facts['card_number'] ?: 'PENDING',
                'status' => 'draft',
                'created_by' => $actorId,
            ]);
        }

        $card->person_type = $facts['person_type'];
        $card->branch_id = $facts['branch_id'];
        $card->school_id = $facts['school_id'];
        $card->card_number = $facts['card_number'] ?: $card->card_number;

        if (! in_array($card->status, ['lost', 'suspended', 'expired', 'reissued'], true)) {
            if ($facts['ready']) {
                if ($card->status !== 'active') {
                    $card->status = 'active';
                    $card->activated_at = now();
                }
            } else {
                $card->status = 'draft';
                $card->activated_at = null;
            }
        }
        $card->save();

        return [$card, $facts];
    }

    public function present(IdCard $card, array $facts, bool $withQr = true): array
    {
        $token = null;
        $modules = [];
        $expiresIn = 0;
        if ($withQr && $card->status === 'active') {
            $token = $this->sign($card, 60);
            $expiresIn = 60;
            $modules = $this->modules($token);
        }

        return [
            'id' => $card->id,
            'status' => $card->status,
            'person_type' => $facts['person_type'],
            'school_name' => $facts['school_name'],
            'full_name' => $facts['full_name'],
            'card_number' => $facts['card_number'],
            'role_label' => $facts['role_label'],
            'detail_label' => $facts['detail_label'],
            'detail_value' => $facts['detail_value'],
            'academic_year' => $facts['academic_year'],
            'photo_url' => $facts['photo_url'],
            'blood_group' => $facts['blood_group'],
            'emergency_phone' => $facts['emergency_phone'],
            'bus_route' => $facts['bus_route'],
            'blocked_reason' => $facts['blocked_reason'],
            'qr_token' => $token,
            'qr_expires_in' => $expiresIn,
            'qr_modules' => $modules,
        ];
    }

    public function sign(IdCard $card, int $seconds): string
    {
        $body = $card->id.'.'.(time() + $seconds).'.'.substr(bin2hex(random_bytes(4)), 0, 8);
        $sig = $this->signature($body);

        return $body.'.'.$sig;
    }

    public function verify(string $token, ?User $scanner, string $gate): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 4) {
            return $this->log(null, $scanner, $gate, 'denied', 'This code is not a school ID.');
        }
        [$id, $exp, $nonce, $sig] = $parts;
        $body = $id.'.'.$exp.'.'.$nonce;
        if (! hash_equals($this->signature($body), $sig)) {
            return $this->log(null, $scanner, $gate, 'denied', 'This code is not a school ID.');
        }
        if (! ctype_digit($exp) || (int) $exp < time()) {
            $card = IdCard::query()->find((int) $id);
            return $this->log($card, $scanner, $gate, 'denied', 'This code has expired. Ask the person to refresh the card.');
        }
        $card = IdCard::query()->find((int) $id);
        if (! $card) {
            return $this->log(null, $scanner, $gate, 'denied', 'This card is no longer on file.');
        }
        if ($card->status !== 'active') {
            $reason = match ($card->status) {
                'lost' => 'This card was reported lost.',
                'suspended' => 'This card is suspended.',
                'expired' => 'This card has expired.',
                'reissued' => 'This card was replaced.',
                default => 'This card is not active.',
            };

            return $this->log($card, $scanner, $gate, 'denied', $reason);
        }

        $user = User::query()->find($card->user_id);
        $facts = $user ? $this->factsFor($user, $card->academic_year_id) : [];
        $logged = $this->log($card, $scanner, $gate, 'allowed', 'Active card');
        $logged['card'] = [
            'full_name' => $facts['full_name'] ?? '',
            'role_label' => $facts['role_label'] ?? $card->person_type,
            'detail_value' => $facts['detail_value'] ?? '',
            'card_number' => $card->card_number,
            'photo_url' => $facts['photo_url'] ?? null,
            'school_name' => $facts['school_name'] ?? '',
        ];

        return $logged;
    }

    public function setStatus(IdCard $card, string $status, ?int $actorId = null): IdCard
    {
        if ($status === 'reissued') {
            $user = User::query()->findOrFail($card->user_id);
            [$fresh] = $this->ensureForUser($user, $card->academic_year_id, $actorId);
            $card->status = 'reissued';
            $card->replaced_by_id = $fresh->id;
            $card->save();

            return $fresh;
        }

        $card->status = $status;
        if ($status === 'active') {
            $card->activated_at = now();
        }
        $card->save();

        return $card;
    }

    public function modules(string $token): array
    {
        $qr = Encoder::encode($token, ErrorCorrectionLevel::M());
        $matrix = $qr->getMatrix();
        $rows = [];
        for ($y = 0; $y < $matrix->getHeight(); $y++) {
            $row = '';
            for ($x = 0; $x < $matrix->getWidth(); $x++) {
                $row .= ((int) $matrix->get($x, $y)) === 1 ? '1' : '0';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function signature(string $body): string
    {
        $raw = hash_hmac('sha256', $body, (string) config('app.key'), true);

        return rtrim(strtr(base64_encode(substr($raw, 0, 16)), '+/', '-_'), '=');
    }

    private function log(?IdCard $card, ?User $scanner, string $gate, string $result, string $reason): array
    {
        IdCardScan::query()->create([
            'id_card_id' => $card?->id,
            'scanner_user_id' => $scanner?->id,
            'gate_name' => $gate !== '' ? $gate : 'Desk',
            'result' => $result,
            'reason' => $reason,
        ]);

        return [
            'allowed' => $result === 'allowed',
            'result' => $result,
            'reason' => $reason,
        ];
    }

    public function factsFor(User $user, ?int $academicYearId): array
    {
        $student = Student::query()->where('user_id', $user->id)->first();
        $teacher = Teacher::query()->where('user_id', $user->id)->first();
        $yearId = $academicYearId ?: ($student->academic_year_id ?? null);
        $yearName = $yearId ? AcademicYear::query()->where('id', $yearId)->value('name') : null;
        $branchId = $student->branch_id ?? $teacher->branch_id ?? $user->branch_id;
        $branch = $branchId ? Branch::query()->find($branchId) : null;

        if ($student) {
            $photo = $student->profile_picture;
            $missing = [];
            if (! $photo) {
                $missing[] = 'a profile photo';
            }
            if (! $student->admission_number) {
                $missing[] = 'an admission number';
            }
            if (! $student->grade || ! $student->section) {
                $missing[] = 'a grade and section';
            }
            $bus = $this->busLabel($student->id);

            return [
                'person_type' => 'student',
                'branch_id' => $branchId,
                'school_id' => $student->school_id ?? $branch?->school_id,
                'academic_year_id' => $yearId,
                'academic_year' => $yearName,
                'school_name' => $branch?->name ?: 'School',
                'full_name' => trim($user->first_name.' '.$user->last_name),
                'card_number' => (string) $student->admission_number,
                'role_label' => 'Student',
                'detail_label' => 'Grade / Section',
                'detail_value' => trim('Grade '.$student->grade.' · '.$student->section),
                'photo_url' => $photo,
                'blood_group' => $student->blood_group ?: null,
                'emergency_phone' => $student->emergency_contact_phone ?: null,
                'bus_route' => $bus,
                'ready' => $missing === [],
                'blocked_reason' => $missing === [] ? null : 'Add '.implode(', ', $missing).' before this card can be used.',
            ];
        }

        if ($teacher) {
            $photo = $teacher->profile_picture ?: $user->avatar;
            $missing = [];
            if (! $photo) {
                $missing[] = 'a profile photo';
            }
            if (! $teacher->employee_id) {
                $missing[] = 'an employee id';
            }
            if (! $teacher->designation) {
                $missing[] = 'a designation';
            }

            return [
                'person_type' => 'teacher',
                'branch_id' => $branchId,
                'school_id' => $teacher->school_id ?? $branch?->school_id,
                'academic_year_id' => $yearId,
                'academic_year' => $yearName,
                'school_name' => $branch?->name ?: 'School',
                'full_name' => trim($user->first_name.' '.$user->last_name),
                'card_number' => (string) $teacher->employee_id,
                'role_label' => $teacher->category_type === 'Teaching' || $user->role === 'Teacher' ? 'Teacher' : 'Staff',
                'detail_label' => 'Designation',
                'detail_value' => (string) ($teacher->designation ?: ''),
                'photo_url' => $photo,
                'blood_group' => $teacher->blood_group ?: null,
                'emergency_phone' => $teacher->alternate_phone ?: $user->phone ?: null,
                'bus_route' => null,
                'ready' => $missing === [],
                'blocked_reason' => $missing === [] ? null : 'Add '.implode(', ', $missing).' before this card can be used.',
            ];
        }

        return [
            'person_type' => 'staff',
            'branch_id' => $branchId,
            'school_id' => $branch?->school_id,
            'academic_year_id' => $yearId,
            'academic_year' => $yearName,
            'school_name' => $branch?->name ?: 'School',
            'full_name' => trim($user->first_name.' '.$user->last_name),
            'card_number' => '',
            'role_label' => (string) ($user->role ?: 'Staff'),
            'detail_label' => 'Role',
            'detail_value' => (string) ($user->role ?: ''),
            'photo_url' => $user->avatar,
            'blood_group' => null,
            'emergency_phone' => $user->phone,
            'bus_route' => null,
            'ready' => false,
            'blocked_reason' => 'This account has no student or staff record, so a card cannot be issued.',
        ];
    }

    private function busLabel(int $studentId): ?string
    {
        $row = StudentTransport::query()
            ->where('student_id', $studentId)
            ->where('status', 'Active')
            ->first();
        if (! $row) {
            return null;
        }
        $route = $row->route_id
            ? DB::table('transport_routes')->where('id', $row->route_id)->value('route_name')
            : null;
        $parts = array_filter([$route, $row->stop_name]);

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
