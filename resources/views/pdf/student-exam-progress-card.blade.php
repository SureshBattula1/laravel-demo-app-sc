<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Progress Report</title>
    @include('pdf.partials.report-brand-styles')
</head>
<body>
@foreach($sections as $sectionIndex => $section)
    @php
        $summary = $section['summary'] ?? [];
    @endphp
    <div class="report-page">
        @include('pdf.partials.report-header')
        @include('pdf.partials.report-title-block')
        @include('pdf.partials.report-student-card')

        @include('pdf.partials.report-section-pill', [
            'pillLabel' => $section['exam_pill_label'] ?? strtoupper($section['exam_title']),
            'showYearSub' => true,
        ])

        <table class="marks-table">
            <thead>
                <tr>
                    <th class="col-subject">Subject Name</th>
                    <th class="col-date">Date</th>
                    <th class="col-marks">Marks</th>
                    <th class="col-desc">Description</th>
                    <th class="col-grade">Grade</th>
                </tr>
            </thead>
            <tbody>
                @foreach($section['rows'] as $row)
                    <tr>
                        <td class="col-subject" title="{{ $row['subject'] }}">{{ $row['subject'] }}</td>
                        <td class="col-date">{{ $row['date'] }}</td>
                        <td class="col-marks">{{ $row['marks'] }}</td>
                        <td class="col-desc" title="{{ $row['description'] }}">{{ $row['description'] }}</td>
                        <td class="col-grade">{{ $row['grade'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="summary-table" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <div class="summary-label">Total</div>
                    <div class="summary-value">{{ $summary['total_label'] ?? '—' }}</div>
                </td>
                <td>
                    <div class="summary-label">Percentage</div>
                    <div class="summary-value">{{ $summary['percentage_label'] ?? '—' }}</div>
                </td>
                <td>
                    <div class="summary-label">Grade</div>
                    <div class="summary-value grade-highlight">{{ $summary['grade'] ?? '—' }}</div>
                </td>
            </tr>
        </table>

        @include('pdf.partials.report-footer-signatures')
        @include('pdf.partials.report-bottom-wave')
    </div>
@endforeach
</body>
</html>
