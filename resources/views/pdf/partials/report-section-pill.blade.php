        <div class="exam-pill-wrap">
            <span class="exam-pill">{{ $pillLabel }}</span>
        </div>
        @if(!empty($showYearSub))
            <p class="exam-year-sub">{{ $report['academic_year'] }}</p>
        @endif
