    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 10mm 14mm 10mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #121826;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        .report-page {
            position: relative;
            width: 100%;
            min-height: 260mm;
            padding: 0 0 28px 0;
            page-break-after: always;
            overflow: hidden;
        }

        .report-page:last-child {
            page-break-after: auto;
        }

        .header-wrap {
            position: relative;
            padding: 8px 0 16px 0;
            margin-bottom: 8px;
        }

        .header-corner {
            position: absolute;
            top: 0;
            right: 0;
            width: 120px;
            height: 72px;
            overflow: hidden;
            z-index: 0;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            position: relative;
            z-index: 1;
        }

        .header-table td {
            vertical-align: top;
        }

        .logo-cell {
            width: 72px;
        }

        .logo-img {
            width: 58px;
            height: 58px;
            object-fit: contain;
        }

        .school-name {
            font-size: 18px;
            font-weight: bold;
            color: #0B2E78;
            margin: 0;
            letter-spacing: 0.5px;
            line-height: 1.2;
        }

        .school-branch {
            font-size: 12px;
            font-weight: bold;
            color: #12399E;
            margin: 2px 0 0 0;
        }

        .school-tagline {
            font-size: 10px;
            color: #667085;
            margin: 4px 0 0 0;
        }

        .address-block {
            text-align: right;
            font-size: 9px;
            color: #667085;
            line-height: 1.45;
        }

        .report-title-block {
            text-align: center;
            margin: 18px 0 16px 0;
        }

        .report-title {
            font-size: 26px;
            font-weight: bold;
            color: #0B2E78;
            margin: 0;
            letter-spacing: 1px;
        }

        .report-subtitle {
            font-size: 11px;
            color: #667085;
            margin: 6px 0 0 0;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .student-card {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #DCE5F2;
            border-radius: 12px;
            background: #FFFFFF;
            margin-bottom: 18px;
            overflow: hidden;
        }

        .student-card td {
            padding: 14px 16px;
            vertical-align: middle;
        }

        .photo-cell {
            width: 88px;
        }

        .student-photo {
            width: 70px;
            height: 70px;
            border-radius: 10px;
            border: 1px solid #DCE5F2;
            object-fit: cover;
        }

        .photo-placeholder {
            width: 70px;
            height: 70px;
            border-radius: 10px;
            border: 1px solid #DCE5F2;
            background: #F3F7FF;
            text-align: center;
            line-height: 70px;
            font-size: 9px;
            color: #667085;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 3px 0;
            font-size: 11px;
        }

        .info-label {
            width: 38%;
            color: #667085;
            font-size: 10px;
        }

        .info-value {
            color: #0B2E78;
            font-weight: bold;
            font-size: 11px;
        }

        .exam-pill-wrap {
            text-align: center;
            margin: 0 0 6px 0;
        }

        .exam-pill {
            display: inline-block;
            background: #0F3CC9;
            color: #ffffff;
            font-size: 12px;
            font-weight: bold;
            padding: 10px 28px;
            border-radius: 40px;
            letter-spacing: 0.5px;
        }

        .exam-year-sub {
            text-align: center;
            font-size: 10px;
            color: #667085;
            margin: 0 0 14px 0;
        }

        table.data-table,
        table.marks-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1px solid #DCE5F2;
        }

        table.data-table thead th,
        table.marks-table thead th {
            background: #0B2E78;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            padding: 10px 6px;
            text-align: left;
            border: 1px solid #0B2E78;
        }

        table.data-table tbody td,
        table.marks-table tbody td {
            font-size: 10px;
            padding: 9px 6px;
            border-bottom: 1px solid #DCE5F2;
            color: #121826;
            vertical-align: middle;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        table.data-table tbody tr:nth-child(even) td,
        table.marks-table tbody tr:nth-child(even) td {
            background: #F3F7FF;
        }

        .col-subject { width: 32%; }
        .col-date { width: 15%; }
        .col-marks { width: 12%; }
        .col-desc { width: 26%; }
        .col-grade { width: 15%; text-align: center; }

        .col-fee-type { width: 22%; }
        .col-due-date { width: 14%; }
        .col-money { width: 14%; text-align: right; }
        .col-status { width: 22%; }
        .col-receipt { width: 18%; }
        .col-method { width: 14%; }

        .section-heading {
            font-size: 11px;
            font-weight: bold;
            color: #0B2E78;
            margin: 16px 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .empty-note {
            font-size: 10px;
            color: #667085;
            font-style: italic;
            margin: 0 0 12px 0;
        }

        .summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin: 16px 0 24px 0;
        }

        .summary-table td {
            width: 33.33%;
            background: #EAF2FF;
            border: 1px solid #DCE5F2;
            border-radius: 10px;
            text-align: center;
            padding: 12px 8px;
            vertical-align: middle;
        }

        .summary-label {
            font-size: 9px;
            color: #667085;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-bottom: 4px;
        }

        .summary-value {
            font-size: 16px;
            font-weight: bold;
            color: #0F3CC9;
        }

        .summary-value.grade-highlight {
            color: #0B2E78;
            font-size: 18px;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .signature-table td {
            width: 50%;
            vertical-align: bottom;
            padding: 0 12px;
        }

        .sig-title {
            font-size: 10px;
            font-weight: bold;
            color: #12399E;
            margin-bottom: 28px;
        }

        .sig-line {
            border-top: 1px solid #667085;
            font-size: 9px;
            color: #667085;
            padding-top: 4px;
            text-align: center;
        }

        .bottom-wave {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 36px;
            line-height: 0;
        }

        .footer-meta {
            position: absolute;
            bottom: 42px;
            right: 0;
            font-size: 8px;
            color: #667085;
        }

        .receipt-highlight {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #DCE5F2;
            margin-bottom: 14px;
        }

        .receipt-highlight td {
            padding: 10px 12px;
            font-size: 11px;
            border-bottom: 1px solid #DCE5F2;
        }

        .receipt-highlight td.label {
            width: 38%;
            color: #667085;
            font-weight: bold;
        }

        .receipt-highlight td.value {
            color: #0B2E78;
            font-weight: bold;
        }

        .receipt-amount-row td {
            background: #EAF2FF;
            font-size: 13px;
        }

        @media print {
            body { background: #fff; }
            .report-page { page-break-inside: avoid; }
        }
    </style>
