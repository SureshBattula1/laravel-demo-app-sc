<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Fee Payment Receipt</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            margin: 0;
            padding: 12px;
            color: #333;
        }
        .receipt-wrapper {
            border: 1px solid #ddd;
            padding: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header h1 {
            font-size: 16px;
            margin: 0 0 4px 0;
        }
        .header h2 {
            font-size: 12px;
            margin: 0;
            color: #555;
        }
        .meta-row {
            margin-top: 8px;
            font-size: 10px;
        }
        .meta-row span {
            display: inline-block;
            margin-right: 12px;
        }
        .section-title {
            font-weight: bold;
            margin-top: 10px;
            margin-bottom: 4px;
            border-bottom: 1px solid #eee;
            padding-bottom: 2px;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .details-table td {
            padding: 3px 4px;
            vertical-align: top;
        }
        .details-table td.label {
            width: 35%;
            color: #555;
            font-weight: bold;
        }
        .amount-row td {
            padding-top: 4px;
            border-top: 1px solid #ddd;
        }
        .amount-label {
            text-align: right;
        }
        .amount-value {
            text-align: right;
            font-weight: bold;
        }
        .footer {
            margin-top: 10px;
            font-size: 9px;
            text-align: center;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="receipt-wrapper">
        <div class="header">
            <h1>Payment Receipt</h1>
            <h2>{{ $receiptNumber }}</h2>
            <div class="meta-row">
                <span><strong>Date:</strong> {{ \Carbon\Carbon::parse($payment->payment_date)->format('d-m-Y') }}</span>
                <span><strong>Generated:</strong> {{ now()->format('d-m-Y H:i') }}</span>
            </div>
        </div>

        <div class="section-title">Student Information</div>
        <table class="details-table">
            <tr>
                <td class="label">Student Name</td>
                <td>{{ $studentName }}</td>
            </tr>
            @if(optional($payment->student)->admission_number)
                <tr>
                    <td class="label">Admission Number</td>
                    <td>{{ $payment->student->admission_number }}</td>
                </tr>
            @endif
            @if(!empty($studentGradeLabel))
                <tr>
                    <td class="label">Grade</td>
                    <td>{{ $studentGradeLabel }}</td>
                </tr>
            @endif
            @if(!empty($studentSection))
                <tr>
                    <td class="label">Section</td>
                    <td>{{ $studentSection }}</td>
                </tr>
            @endif
        </table>

        @php
            $feeAmount = (float) (optional($payment->feeStructure)->amount ?? 0);
            $currentDiscount = (float) ($payment->discount_amount ?? 0);
            $previousDiscount = isset($previousPayments) ? (float) $previousPayments->sum('discount_amount') : 0;
            $totalDiscount = $currentDiscount + $previousDiscount;
            $netFee = max(0, $feeAmount - $totalDiscount);
        @endphp

        <div class="section-title">Fee Details</div>
        <table class="details-table">
            <tr>
                <td class="label">Fee Type</td>
                <td>{{ optional($payment->feeStructure)->fee_type ?? 'General Fee' }}</td>
            </tr>
            @if(optional($payment->feeStructure)->grade)
                <tr>
                    <td class="label">Grade</td>
                    <td>{{ $studentGradeLabel ?? $payment->feeStructure->grade }}</td>
                </tr>
            @endif
            @if(optional($payment->feeStructure)->academic_year)
                <tr>
                    <td class="label">Academic Year</td>
                    <td>{{ $payment->feeStructure->academic_year }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">Fee Amount</td>
                <td>{{ number_format($feeAmount, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Total Discount</td>
                <td>-{{ number_format($totalDiscount, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Amount to be Paid (After Discount)</td>
                <td>{{ number_format($netFee, 2) }}</td>
            </tr>
            @if($payment->payment_status === 'Partial' && isset($alreadyPaidExcludingCurrent) && $alreadyPaidExcludingCurrent > 0 && isset($paymentHistory) && $paymentHistory->count() > 1)
                <tr>
                    <td class="label">Already Paid (Late fee not counted)</td>
                    <td>{{ number_format($alreadyPaidExcludingCurrent, 2) }}</td>
                </tr>
            @endif
            @if(isset($totalLateFee) && $totalLateFee > 0)
                <tr>
                    <td class="label">Total Late Fee</td>
                    <td>+{{ number_format($totalLateFee, 2) }}</td>
                </tr>
            @endif
            @if(isset($alreadyPaidExcludingCurrent) && isset($paymentHistory) && $paymentHistory->count() > 1)
                <tr>
                    <td class="label">Total Amount Paid (Late fee not counted)</td>
                    <td><strong>{{ number_format($alreadyPaidExcludingCurrent, 2) }}</strong></td>
                </tr>
            @endif
        </table>

        <div class="section-title">Payment Details</div>
        <table class="details-table">
            <tr>
                <td class="label">Payment Date</td>
                <td>{{ \Carbon\Carbon::parse($payment->payment_date)->format('d-m-Y H:i') }}</td>
            </tr>
            <tr>
                <td class="label">Payment Method</td>
                <td>{{ $payment->payment_method }}</td>
            </tr>
            <tr>
                <td class="label">Payment Status</td>
                <td>{{ $payment->payment_status }}</td>
            </tr>
            @if($payment->transaction_id)
                <tr>
                    <td class="label">Transaction ID</td>
                    <td>{{ $payment->transaction_id }}</td>
                </tr>
            @endif
            @if($payment->remarks)
                <tr>
                    <td class="label">Remarks</td>
                    <td>{{ $payment->remarks }}</td>
                </tr>
            @endif
            <tr>
                <td class="amount-label">Amount Paid</td>
                <td class="amount-value">{{ number_format($payment->amount_paid, 2) }}</td>
            </tr>
            <tr>
                <td class="amount-label">Discount (This Payment)</td>
                <td class="amount-value">-{{ number_format($payment->discount_amount ?? 0, 2) }}</td>
            </tr>
            @if($payment->late_fee > 0)
                <tr>
                    <td class="amount-label">Late Fee</td>
                    <td class="amount-value">+{{ number_format($payment->late_fee, 2) }}</td>
                </tr>
            @endif
            @php
                $thisPaymentTotal = (float) $payment->amount_paid + (float) ($payment->late_fee ?? 0);
            @endphp
            <tr class="amount-row">
                <td class="amount-label">Total Amount (This Payment)</td>
                <td class="amount-value">{{ number_format($thisPaymentTotal, 2) }}</td>
            </tr>
        </table>

        @if(isset($previousPayments) && $previousPayments->count() > 0)
            <div class="section-title">Previous Payments</div>
            <table class="details-table">
                <tr>
                    <td class="label">Date</td>
                    <td class="label">Payment Method</td>
                    <td class="label">Amount Paid</td>
                    <td class="label">Discount</td>
                    <td class="label">Late Fee</td>
                    <td class="label">Total</td>
                </tr>
                @foreach($previousPayments as $prev)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($prev->payment_date)->format('d-m-Y H:i') }}</td>
                        <td>{{ $prev->payment_method ?? '—' }}</td>
                        <td>{{ number_format($prev->amount_paid ?? 0, 2) }}</td>
                        <td>-{{ number_format($prev->discount_amount ?? 0, 2) }}</td>
                        <td>@if(($prev->late_fee ?? 0) > 0)+{{ number_format($prev->late_fee, 2) }}@else—@endif</td>
                        <td>{{ number_format($prev->total_amount ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        <div class="footer">
            This is a system-generated receipt. No signature is required.
        </div>
    </div>
</body>
</html>

