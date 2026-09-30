        <table class="student-card" cellpadding="0" cellspacing="0">
            <tr>
                <td class="photo-cell">
                    @if(!empty($student['photo_data_uri']))
                        <img src="{{ $student['photo_data_uri'] }}" class="student-photo" alt="Student photo">
                    @else
                        <div class="photo-placeholder">Photo</div>
                    @endif
                </td>
                <td>
                    <table class="info-table" cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="info-label">Student Name</td>
                            <td class="info-value">: {{ $student['name'] }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Class</td>
                            <td class="info-value">: {{ $student['class'] }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Admission No</td>
                            <td class="info-value">: {{ $student['admission_number'] }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">Academic Year</td>
                            <td class="info-value">: {{ $student['academic_year'] }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
