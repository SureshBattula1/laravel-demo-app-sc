<?php

namespace App\Services;

use App\Models\FeeAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditService
{
    /**
     * Log a fee-related action
     */
    public function log($action, $studentId, $data = [], ?Request $request = null): FeeAuditLog
    {
        try {
            $auditData = [
                'action' => $action,
                'student_id' => $studentId,
                'amount_before' => $data['amount_before'] ?? 0,
                'amount_after' => $data['amount_after'] ?? 0,
                'action_amount' => $data['action_amount'] ?? 0,
                'reason' => $data['reason'] ?? null,
                'metadata' => $data['metadata'] ?? [],
                'fee_payment_id' => $data['fee_payment_id'] ?? null,
                'fee_due_id' => $data['fee_due_id'] ?? null,
                'created_by' => $data['user_id'] ?? ($request ? $request->user()->id : null),
            ];

            // Add request information if available
            if ($request) {
                $auditData['ip_address'] = $request->ip();
                $auditData['user_agent'] = $request->userAgent();
                $auditData['session_id'] = $request->session()->getId();
                $auditData['request_method'] = $request->method();
                $auditData['request_endpoint'] = $request->path();
            }

            $auditLog = FeeAuditLog::create($auditData);

            // Also record in the unified enterprise audit_logs stream
            app(\App\Services\SystemAuditLogger::class)->record([
                'module' => 'fees',
                'action' => strtolower((string) $action),
                'severity' => 'warning',
                'auditable_type' => 'StudentFee',
                'auditable_id' => (string) $studentId,
                'description' => "Fee audit ({$action}) for Student #{$studentId}".(! empty($data['reason']) ? ": {$data['reason']}" : ''),
                'old_values' => ['amount_before' => $auditData['amount_before']],
                'new_values' => [
                    'amount_after' => $auditData['amount_after'],
                    'action_amount' => $auditData['action_amount'],
                    'fee_payment_id' => $auditData['fee_payment_id'],
                    'fee_due_id' => $auditData['fee_due_id'],
                ],
                'changed_fields' => ['amount_after', 'action_amount'],
            ], $request);

            return $auditLog;

        } catch (\Exception $e) {
            Log::error('Error creating audit log', [
                'action' => $action,
                'student_id' => $studentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get audit logs for a student
     */
    public function getStudentAuditLogs($studentId, $filters = [])
    {
        $query = FeeAuditLog::where('student_id', $studentId)
            ->with(['creator', 'feePayment', 'feeDue']);

        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get audit logs by action type
     */
    public function getAuditLogsByAction($action, $filters = [])
    {
        $query = FeeAuditLog::where('action', $action)
            ->with(['student', 'creator', 'feePayment', 'feeDue']);

        if (isset($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }
}
