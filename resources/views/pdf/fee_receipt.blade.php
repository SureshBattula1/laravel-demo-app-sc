<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Receipt</title>
    @include('pdf.partials.report-brand-styles')
</head>
<body>
<div class="report-page">
    @include('pdf.partials.report-header')
    @include('pdf.partials.report-title-block')
    @include('pdf.partials.report-student-card')

    @include('pdf.partials.report-section-pill', [
        'pillLabel' => 'RECEIPT ' . ($receiptNumber ?? ''),
        'showYearSub' => false,
    ])

    @php
        $feeAmount = (float) (optional($payment->feeStructure)->amount ?? 0);
        $currentDiscount = (float) ($payment->discount_amount ?? 0);
        $previousDiscount = isset($previousPayments) ? (float) $previousPayments->sum('discount_amount') : 0;
        $totalDiscount = $currentDiscount + $previousDiscount;
        $netFee = max(0, $feeAmount - $totalDiscount);
        $thisPaymentTotal = (float) $payment->amount_paid + (float) ($payment->late_fee ?? 0);
    @endphp

    <table class="receipt-highlight" cellpadding="0" cellspacing="0">
        <tr>
            <td class="label">Receipt number</td>
            <td class="value">{{ $receiptNumber }}</td>
        </tr>
        <tr>
            <td class="label">Payment date</td>
            <td class="value">{{ \Carbon\Carbon::parse($payment->payment_date)->format('d-m-Y H:i') }}</td>
        </tr>
        <tr>
            <td class="label">Fee type</td>
            <td class="value">{{ optional($payment->feeStructure)->fee_type ?? 'General Fee' }}</td>
        </tr>
        <tr>
            <td class="label">Payment method</td>
            <td class="value">{{ $payment->payment_method }}</td>
        </tr>
        <tr>
            <td class="label">Payment status</td>
            <td class="value">{{ $payment->payment_status }}</td>
        </tr>
        @if($payment->transaction_id)
            <tr>
                <td class="label">Transaction ID</td>
                <td class="value">{{ $payment->transaction_id }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Fee amount</td>
            <td class="value">{{ number_format($feeAmount, 2) }}</td>
        </tr>
        @if($totalDiscount > 0)
            <tr>
                <td class="label">Total discount</td>
                <td class="value">-{{ number_format($totalDiscount, 2) }}</td>
            </tr>
        @endif
        @if($payment->late_fee > 0)
            <tr>
                <td class="label">Late fee (this payment)</td>
                <td class="value">+{{ number_format($payment->late_fee, 2) }}</td>
            </tr>
        @endif
        <tr class="receipt-amount-row">
            <td class="label">Amount paid (this payment)</td>
            <td class="value">₹{{ number_format($thisPaymentTotal, 2) }}</td>
        </tr>
    </table>

    @if($payment->remarks)
        <p class="section-heading">Remarks</p>
        <p class="empty-note" style="font-style: normal;">{{ $payment->remarks }}</p>
    @endif

    @if(isset($previousPayments) && $previousPayments->count() > 0)
        <p class="section-heading">Previous payments (same fee)</p>
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-due-date">Date</th>
                    <th class="col-method">Method</th>
                    <th class="col-money">Paid</th>
                    <th class="col-money">Discount</th>
                    <th class="col-money">Late fee</th>
                    <th class="col-money">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($previousPayments as $prev)
                    <tr>
                        <td class="col-due-date">{{ \Carbon\Carbon::parse($prev->payment_date)->format('d-m-Y') }}</td>
                        <td class="col-method">{{ $prev->payment_method ?? '—' }}</td>
                        <td class="col-money">{{ number_format($prev->amount_paid ?? 0, 2) }}</td>
                        <td class="col-money">-{{ number_format($prev->discount_amount ?? 0, 2) }}</td>
                        <td class="col-money">@if(($prev->late_fee ?? 0) > 0)+{{ number_format($prev->late_fee, 2) }}@else—@endif</td>
                        <td class="col-money">{{ number_format($prev->total_amount ?? 0, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @include('pdf.partials.report-footer-signatures')
    @include('pdf.partials.report-bottom-wave')
</div>
</body>
</html>
