<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $transaction->type }} Receipt</title>
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
            font-size: 13px;
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
            <h1>{{ $transaction->type }} Receipt</h1>
            <h2>{{ $transaction->transaction_number }}</h2>
            <div class="meta-row">
                <span><strong>Date:</strong> {{ \Carbon\Carbon::parse($transaction->transaction_date)->format('d-m-Y') }}</span>
                <span><strong>Generated:</strong> {{ now()->format('d-m-Y H:i') }}</span>
            </div>
        </div>

        <div class="section-title">Transaction Details</div>
        <table class="details-table">
            <tr>
                <td class="label">Transaction #</td>
                <td>{{ $transaction->transaction_number }}</td>
            </tr>
            <tr>
                <td class="label">Date</td>
                <td>{{ \Carbon\Carbon::parse($transaction->transaction_date)->format('d-m-Y') }}</td>
            </tr>
            <tr>
                <td class="label">Type</td>
                <td>{{ $transaction->type }}</td>
            </tr>
            <tr>
                <td class="label">Category</td>
                <td>{{ optional($transaction->category)->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Branch</td>
                <td>{{ optional($transaction->branch)->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Party</td>
                <td>{{ $transaction->party_name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="label">Payment Method</td>
                <td>{{ $transaction->payment_method ?? '—' }}</td>
            </tr>
            @if($transaction->payment_reference)
            <tr>
                <td class="label">Payment Reference</td>
                <td>{{ $transaction->payment_reference }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">Description</td>
                <td>{{ $transaction->description ?? '—' }}</td>
            </tr>
            @if($transaction->notes)
            <tr>
                <td class="label">Notes</td>
                <td>{{ $transaction->notes }}</td>
            </tr>
            @endif
        </table>

        <div class="section-title">Amount</div>
        <table class="details-table">
            <tr class="amount-row">
                <td class="amount-label">{{ $transaction->type }} Amount</td>
                <td class="amount-value">₹ {{ number_format((float) $transaction->amount, 2) }}</td>
            </tr>
        </table>

        <div class="section-title">Approval</div>
        <table class="details-table">
            <tr>
                <td class="label">Status</td>
                <td>{{ $transaction->status }}</td>
            </tr>
            @if($transaction->approved_at)
            <tr>
                <td class="label">Approved On</td>
                <td>{{ \Carbon\Carbon::parse($transaction->approved_at)->format('d-m-Y H:i') }}</td>
            </tr>
            @endif
            @if($transaction->approvedBy)
            <tr>
                <td class="label">Approved By</td>
                <td>{{ $transaction->approvedBy->full_name ?? '—' }}</td>
            </tr>
            @endif
        </table>

        <div class="footer">
            This is a system-generated receipt for approved transaction. No signature is required.
        </div>
    </div>
</body>
</html>
