<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Fee Statement</title>
    @include('pdf.partials.report-brand-styles')
</head>
<body>
<div class="report-page">
    @include('pdf.partials.report-header')
    @include('pdf.partials.report-title-block')
    @include('pdf.partials.report-student-card')

    @include('pdf.partials.report-section-pill', [
        'pillLabel' => $pillLabel,
        'showYearSub' => false,
    ])

    <table class="summary-table" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <div class="summary-label">Total assessed</div>
                <div class="summary-value">{{ $summary['total_assessed'] ?? '—' }}</div>
            </td>
            <td>
                <div class="summary-label">Total paid</div>
                <div class="summary-value">{{ $summary['total_paid'] ?? '—' }}</div>
            </td>
            <td>
                <div class="summary-label">Balance due</div>
                <div class="summary-value grade-highlight">{{ $summary['balance_due'] ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <p class="section-heading">Outstanding / fee lines</p>
    @if(empty($dueRows))
        <p class="empty-note">No outstanding dues.</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-fee-type">Fee type</th>
                    <th class="col-due-date">Due date</th>
                    <th class="col-money">Assessed</th>
                    <th class="col-money">Paid</th>
                    <th class="col-money">Balance</th>
                    <th class="col-status">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($dueRows as $row)
                    <tr>
                        <td class="col-fee-type" title="{{ $row['fee_type'] }}">{{ $row['fee_type'] }}</td>
                        <td class="col-due-date">{{ $row['due_date'] }}</td>
                        <td class="col-money">{{ $row['assessed'] }}</td>
                        <td class="col-money">{{ $row['paid'] }}</td>
                        <td class="col-money">{{ $row['balance'] }}</td>
                        <td class="col-status">{{ $row['status'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="section-heading">Payment history</p>
    @if(empty($paymentRows))
        <p class="empty-note">No payments recorded.</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th class="col-receipt">Receipt</th>
                    <th class="col-due-date">Date</th>
                    <th class="col-fee-type">Fee type</th>
                    <th class="col-method">Method</th>
                    <th class="col-money">Amount</th>
                    <th class="col-status">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($paymentRows as $row)
                    <tr>
                        <td class="col-receipt" title="{{ $row['receipt_number'] }}">{{ $row['receipt_number'] }}</td>
                        <td class="col-due-date">{{ $row['payment_date'] }}</td>
                        <td class="col-fee-type" title="{{ $row['fee_type'] }}">{{ $row['fee_type'] }}</td>
                        <td class="col-method">{{ $row['method'] }}</td>
                        <td class="col-money">{{ $row['amount'] }}</td>
                        <td class="col-status">{{ $row['status'] }}</td>
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
