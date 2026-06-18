<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Student;
use App\Models\FeeDue;
use App\Models\FeePayment;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FeeNotificationService
{
    /**
     * Send overdue fee alert
     */
    public function sendOverdueFeeAlert(FeeDue $feeDue): void
    {
        try {
            $student = $feeDue->student;
            if (!$student || !$student->user) {
                return;
            }

            $message = "Your {$feeDue->fee_type} fee of ₹" . number_format($feeDue->balance_amount, 2) . 
                      " is overdue by {$feeDue->overdue_days} days. Due date: " . 
                      Carbon::parse($feeDue->due_date)->format('d M Y');

            Notification::create([
                'user_id' => $student->user_id,
                'branch_id' => $student->branch_id,
                'title' => 'Overdue Fee Alert',
                'message' => $message,
                'type' => 'Warning',
                'priority' => $feeDue->overdue_days > 90 ? 'Urgent' : 'High',
                'status' => 'Pending',
                'action_url' => '/students/' . $student->id . '/fees',
                'metadata' => [
                    'fee_due_id' => $feeDue->id,
                    'fee_type' => $feeDue->fee_type,
                    'balance_amount' => $feeDue->balance_amount,
                    'overdue_days' => $feeDue->overdue_days
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending overdue fee alert', [
                'fee_due_id' => $feeDue->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send promotion notification with fee summary
     */
    public function sendPromotionNotification($studentId, $fromGrade, $toGrade, $feeSummary): void
    {
        try {
            $student = Student::find($studentId);
            if (!$student || !$student->user) {
                return;
            }

            $feeBreakdown = '';
            $totalAmount = 0;

            if (!empty($feeSummary['pending_fees_breakdown'])) {
                foreach ($feeSummary['pending_fees_breakdown'] as $fee) {
                    $feeBreakdown .= "\n• {$fee['fee_type']}: ₹" . number_format($fee['total_balance_amount'], 2);
                    if ($fee['overdue_days'] > 0) {
                        $feeBreakdown .= " ({$fee['overdue_days']} days overdue)";
                    }
                    $totalAmount += $fee['total_balance_amount'];
                }
            }

            $message = "Student has been promoted from {$fromGrade} to {$toGrade}.\n\n";
            
            if ($totalAmount > 0) {
                $message .= "The following pending fees from {$fromGrade} have been carried forward:\n";
                $message .= $feeBreakdown;
                $message .= "\n\nTotal Carried Forward: ₹" . number_format($totalAmount, 2);
                $message .= "\n\nPlease clear these dues at your earliest convenience.";
            } else {
                $message .= "No pending fees to carry forward.";
            }

            Notification::create([
                'user_id' => $student->user_id,
                'branch_id' => $student->branch_id,
                'title' => "Promotion to {$toGrade}",
                'message' => $message,
                'type' => 'Info',
                'priority' => 'Medium',
                'status' => 'Pending',
                'action_url' => '/students/' . $student->id . '/fees',
                'metadata' => [
                    'from_grade' => $fromGrade,
                    'to_grade' => $toGrade,
                    'fees_carried_forward' => $totalAmount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending promotion notification', [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send payment reminder
     */
    public function sendPaymentReminder(FeeDue $feeDue): void
    {
        try {
            $student = $feeDue->student;
            if (!$student || !$student->user) {
                return;
            }

            $dueDate = is_string($feeDue->due_date) ? Carbon::parse($feeDue->due_date) : $feeDue->due_date;
            $daysUntilDue = now()->diffInDays($dueDate, false);

            if ($daysUntilDue > 0) {
                $message = "Reminder: Your {$feeDue->fee_type} fee of ₹" . 
                          number_format($feeDue->balance_amount, 2) . 
                          " is due on " . $dueDate->format('d M Y') . 
                          " ({$daysUntilDue} days remaining).";
            } else {
                $message = "Your {$feeDue->fee_type} fee of ₹" . 
                          number_format($feeDue->balance_amount, 2) . 
                          " was due on " . $dueDate->format('d M Y') . ". Please pay at your earliest convenience.";
            }

            Notification::create([
                'user_id' => $student->user_id,
                'branch_id' => $student->branch_id,
                'title' => 'Payment Reminder',
                'message' => $message,
                'type' => 'Info',
                'priority' => $daysUntilDue <= 3 ? 'High' : 'Medium',
                'status' => 'Pending',
                'action_url' => '/students/' . $student->id . '/fees',
                'metadata' => [
                    'fee_due_id' => $feeDue->id,
                    'fee_type' => $feeDue->fee_type,
                    'balance_amount' => $feeDue->balance_amount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending payment reminder', [
                'fee_due_id' => $feeDue->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send due date reminder
     */
    public function sendDueDateReminder(FeeDue $feeDue): void
    {
        try {
            $student = $feeDue->student;
            if (!$student || !$student->user) {
                return;
            }

            $dueDate = is_string($feeDue->due_date) ? Carbon::parse($feeDue->due_date) : $feeDue->due_date;
            $daysUntilDue = now()->diffInDays($dueDate, false);

            if ($daysUntilDue <= 3 && $daysUntilDue >= 0) {
                $message = "Reminder: Your {$feeDue->fee_type} fee of ₹" . 
                          number_format($feeDue->balance_amount, 2) . 
                          " is due in {$daysUntilDue} days (" . $dueDate->format('d M Y') . ").";

                Notification::create([
                    'user_id' => $student->user_id,
                    'branch_id' => $student->branch_id,
                    'title' => 'Due Date Reminder',
                    'message' => $message,
                    'type' => 'Warning',
                    'priority' => 'High',
                    'status' => 'Pending',
                    'action_url' => '/students/' . $student->id . '/fees',
                    'metadata' => [
                        'fee_due_id' => $feeDue->id,
                        'fee_type' => $feeDue->fee_type,
                        'due_date' => $feeDue->due_date ? (is_string($feeDue->due_date) ? $feeDue->due_date : $feeDue->due_date->format('Y-m-d')) : null
                    ]
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error sending due date reminder', [
                'fee_due_id' => $feeDue->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send carry-forward notification with fee_type breakdown
     */
    public function sendCarryForwardNotification($studentId, $fromGrade, $toGrade, $carriedFees): void
    {
        try {
            $student = Student::find($studentId);
            if (!$student || !$student->user) {
                return;
            }

            $feeBreakdown = '';
            $totalAmount = 0;

            if (!empty($carriedFees)) {
                $groupedByType = collect($carriedFees)->groupBy('fee_type');
                
                foreach ($groupedByType as $feeType => $fees) {
                    $typeTotal = $fees->sum('balance_amount');
                    $feeBreakdown .= "\n• {$feeType}: ₹" . number_format($typeTotal, 2);
                    $totalAmount += $typeTotal;
                }
            }

            $message = "Fees Carried Forward - Promotion to {$toGrade}\n\n";
            $message .= "Dear Parent,\n\n";
            $message .= "The following pending fees from {$fromGrade} have been carried forward:\n";
            $message .= $feeBreakdown;
            $message .= "\n\nTotal Carried Forward: ₹" . number_format($totalAmount, 2);
            $message .= "\n\nPlease clear these dues at your earliest convenience.";

            Notification::create([
                'user_id' => $student->user_id,
                'branch_id' => $student->branch_id,
                'title' => "Fees Carried Forward - Promotion to {$toGrade}",
                'message' => $message,
                'type' => 'Alert',
                'priority' => 'High',
                'status' => 'Pending',
                'action_url' => '/students/' . $student->id . '/fees',
                'metadata' => [
                    'from_grade' => $fromGrade,
                    'to_grade' => $toGrade,
                    'total_carried_forward' => $totalAmount,
                    'fee_types_count' => $groupedByType->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending carry-forward notification', [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);
        }
    }
}

