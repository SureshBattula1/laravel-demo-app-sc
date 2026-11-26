<?php

namespace App\Http\Controllers;

use App\Models\IdCard;
use App\Models\IdCardScan;
use App\Models\Student;
use App\Models\User;
use App\Services\IdCardService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class IdCardController extends Controller
{
    public function __construct(private IdCardService $cards) {}

    public function me(Request $request)
    {
        $user = $request->user();
        $yearId = $request->attributes->get('academic_year_id');
        [$card, $facts] = $this->cards->ensureForUser($user, $yearId ? (int) $yearId : null, $user->id);

        return response()->json([
            'success' => true,
            'data' => $this->cards->present($card, $facts),
        ]);
    }

    public function index(Request $request)
    {
        if (! $request->user()->hasPermission('id_cards.view')) {
            return $this->denied();
        }

        $yearId = $request->filled('academic_year_id')
            ? (int) $request->academic_year_id
            : $request->attributes->get('academic_year_id');
        $query = Student::query()
            ->with('user')
            ->where('student_status', 'Active');
        if ($request->filled('branch_id')) {
            $branchId = (int) $request->branch_id;
            if (! $this->canAccessBranch($request, $branchId)) {
                return response()->json(['success' => false, 'message' => 'You do not have access to this branch'], 403);
            }
            $query->where('branch_id', $branchId);
        }
        if ($request->filled('grade')) {
            $query->where('grade', $request->grade);
        }
        if ($request->filled('section')) {
            $query->where('section', $request->section);
        }
        if ($yearId) {
            $query->where('academic_year_id', (int) $yearId);
        }

        $rows = $query->orderBy('admission_number')->limit(200)->get()->map(function (Student $student) use ($yearId) {
            if (! $student->user) {
                return null;
            }
            [$card, $facts] = $this->cards->ensureForUser($student->user, $yearId ? (int) $yearId : null);

            return [
                'id' => $card->id,
                'student_name' => $facts['full_name'],
                'admission_number' => $facts['card_number'],
                'grade' => $student->grade,
                'section' => $student->section,
                'status' => $card->status,
                'photo_url' => $facts['photo_url'],
                'blocked_reason' => $facts['blocked_reason'],
            ];
        })->filter()->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function issue(Request $request)
    {
        if (! $request->user()->hasPermission('id_cards.issue')) {
            return $this->denied();
        }
        $yearId = $request->attributes->get('academic_year_id');
        $data = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'grade' => 'nullable|string',
            'section' => 'nullable|string',
            'branch_id' => 'nullable|integer|exists:branches,id',
        ]);

        $users = collect();
        if (! empty($data['user_id'])) {
            $users->push(User::query()->findOrFail($data['user_id']));
        } else {
            $students = Student::query()->where('student_status', 'Active');
            if (! empty($data['branch_id'])) {
                $students->where('branch_id', (int) $data['branch_id']);
            }
            if (! empty($data['grade'])) {
                $students->where('grade', $data['grade']);
            }
            if (! empty($data['section'])) {
                $students->where('section', $data['section']);
            }
            if ($yearId) {
                $students->where('academic_year_id', (int) $yearId);
            }
            $users = User::query()->whereIn('id', $students->pluck('user_id'))->get();
        }

        $issued = 0;
        foreach ($users as $user) {
            $this->cards->ensureForUser($user, $yearId ? (int) $yearId : null, $request->user()->id);
            $issued++;
        }

        return response()->json([
            'success' => true,
            'message' => $issued.' card record'.($issued === 1 ? '' : 's').' updated.',
            'data' => ['count' => $issued],
        ]);
    }

    public function updateStatus(Request $request, int $id)
    {
        if (! $request->user()->hasPermission('id_cards.issue')) {
            return $this->denied();
        }
        $data = $request->validate([
            'status' => 'required|in:active,suspended,lost,reissued',
        ]);
        $card = IdCard::query()->findOrFail($id);
        if ($card->branch_id && ! $this->canAccessBranch($request, (int) $card->branch_id)) {
            return response()->json(['success' => false, 'message' => 'You do not have access to this branch'], 403);
        }
        if ($data['status'] === 'active') {
            $owner = User::query()->findOrFail($card->user_id);
            $facts = $this->cards->factsFor($owner, $card->academic_year_id);
            if (! $facts['ready']) {
                return response()->json([
                    'success' => false,
                    'message' => $facts['blocked_reason'],
                ], 422);
            }
        }
        $card = $this->cards->setStatus($card, $data['status'], $request->user()->id);

        return response()->json([
            'success' => true,
            'message' => 'Card updated.',
            'data' => ['id' => $card->id, 'status' => $card->status],
        ]);
    }

    public function verify(Request $request)
    {
        if (! $request->user()->hasPermission('id_cards.verify')) {
            return $this->denied();
        }
        $data = $request->validate([
            'token' => 'required|string|max:500',
            'gate_name' => 'nullable|string|max:80',
        ]);
        $result = $this->cards->verify($data['token'], $request->user(), $data['gate_name'] ?? 'Desk');

        return response()->json(['success' => true, 'data' => $result]);
    }

    public function scans(int $id)
    {
        $request = request();
        if (! $request->user()->hasPermission('id_cards.view')) {
            return $this->denied();
        }
        $rows = IdCardScan::query()
            ->where('id_card_id', $id)
            ->latest('id')
            ->limit(20)
            ->get(['id', 'gate_name', 'result', 'reason', 'created_at']);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function print(int $id)
    {
        $request = request();
        if (! $request->user()->hasPermission('id_cards.view')) {
            return $this->denied();
        }
        $card = IdCard::query()->findOrFail($id);
        $user = User::query()->findOrFail($card->user_id);
        $facts = $this->cards->factsFor($user, $card->academic_year_id);
        $html = '<html><body style="font-family:sans-serif"><h2>'.e($facts['school_name']).'</h2>'
            .'<p>'.e($facts['role_label']).' identification</p>'
            .'<h1>'.e($facts['full_name']).'</h1>'
            .'<p>'.e($facts['detail_label']).': '.e($facts['detail_value']).'</p>'
            .'<p>ID: '.e($facts['card_number']).'</p>'
            .'<p>Status: '.e($card->status).'</p>'
            .'<p>'.e($facts['academic_year'] ?? '').'</p></body></html>';

        return Pdf::loadHTML($html)->setPaper([0, 0, 242, 153], 'portrait')->stream('id-card-'.$card->id.'.pdf');
    }

    private function denied()
    {
        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to perform this action',
        ], 403);
    }
}
