<?php

namespace App\Http\Controllers;

use App\Models\SmsTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\SmsTemplateTagContextFactory;
use App\Services\SmsTemplateTagRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SmsTemplateController extends Controller
{
    public function index(Request $request, int $branchId)
    {
        if (! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $templates = SmsTemplate::query()
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'templates' => $templates,
                'allowed_tags' => SmsTemplateTagRenderer::allowedTags(),
            ],
        ]);
    }

    public function store(Request $request, int $branchId)
    {
        if (! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:sms_templates,name,NULL,id,branch_id,'.$branchId,
            'body' => 'required|string|max:2000',
            'audience' => 'required|string|in:'.implode(',', SmsTemplate::AUDIENCES),
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $template = SmsTemplate::query()->create([
            'branch_id' => $branchId,
            'name' => $request->input('name'),
            'body' => $request->input('body'),
            'audience' => $request->input('audience'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Template created',
            'data' => ['template' => $template],
        ], 201);
    }

    public function update(Request $request, int $branchId, int $templateId)
    {
        if (! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $template = SmsTemplate::query()
            ->where('branch_id', $branchId)
            ->whereKey($templateId)
            ->firstOrFail();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:sms_templates,name,'.$templateId.',id,branch_id,'.$branchId,
            'body' => 'sometimes|string|max:2000',
            'audience' => 'sometimes|string|in:'.implode(',', SmsTemplate::AUDIENCES),
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $template->fill($request->only(['name', 'body', 'audience', 'is_active']));
        $template->save();

        return response()->json([
            'success' => true,
            'message' => 'Template updated',
            'data' => ['template' => $template->fresh()],
        ]);
    }

    public function destroy(Request $request, int $branchId, int $templateId)
    {
        if (! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $template = SmsTemplate::query()
            ->where('branch_id', $branchId)
            ->whereKey($templateId)
            ->firstOrFail();

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted',
        ]);
    }

    /**
     * Preview rendered body for a sample student or teacher.
     */
    public function preview(
        Request $request,
        int $branchId,
        SmsTemplateTagRenderer $renderer,
        SmsTemplateTagContextFactory $contextFactory
    ) {
        if (! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'body' => 'required_without:template_id|string|max:2000',
            'template_id' => [
                'required_without:body',
                'integer',
                Rule::exists('sms_templates', 'id')->where('branch_id', $branchId),
            ],
            'recipient_type' => 'required|string|in:student,teacher',
            'student_id' => [
                'required_if:recipient_type,student',
                'nullable',
                'integer',
                Rule::exists('students', 'id')
                    ->where('branch_id', $branchId)
                    ->withoutTrashed(),
            ],
            'teacher_id' => [
                'required_if:recipient_type,teacher',
                'nullable',
                'integer',
                Rule::exists('teachers', 'id')
                    ->where('branch_id', $branchId)
                    ->withoutTrashed(),
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $body = $request->input('body');
        if ($request->filled('template_id')) {
            $tpl = SmsTemplate::query()
                ->where('branch_id', $branchId)
                ->whereKey((int) $request->input('template_id'))
                ->firstOrFail();
            $body = $tpl->body;
        }

        $recipientType = $request->input('recipient_type');

        if ($recipientType === 'student') {
            $student = Student::query()
                ->whereKey((int) $request->input('student_id'))
                ->where('branch_id', $branchId)
                ->first();
            if ($student === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found in this branch.',
                    'errors' => ['student_id' => ['Pick a student that belongs to the selected branch.']],
                ], 422);
            }
            $context = $contextFactory->buildForStudent($student);
        } else {
            $teacher = Teacher::query()
                ->whereKey((int) $request->input('teacher_id'))
                ->where('branch_id', $branchId)
                ->first();
            if ($teacher === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher not found in this branch.',
                    'errors' => ['teacher_id' => ['Pick a teacher that belongs to the selected branch.']],
                ], 422);
            }
            $context = $contextFactory->buildForTeacher($teacher);
        }

        $rendered = $renderer->render((string) $body, $context);

        return response()->json([
            'success' => true,
            'data' => [
                'rendered_body' => $rendered,
                'context' => $context,
            ],
        ]);
    }
}
