<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for data exports across all modules.
    | Define column mappings, formatting options, and export settings here.
    |
    */

    'students' => [
        'columns' => [
            'id' => [
                'label' => 'ID',
                'enabled' => false,
                'width' => 10,
            ],
            'admission_number' => [
                'label' => 'Admission Number',
                'enabled' => true,
                'width' => 20,
            ],
            'first_name' => [
                'label' => 'First Name',
                'enabled' => true,
                'width' => 18,
            ],
            'last_name' => [
                'label' => 'Last Name',
                'enabled' => true,
                'width' => 18,
            ],
            'email' => [
                'label' => 'Email',
                'enabled' => true,
                'width' => 25,
            ],
            'phone' => [
                'label' => 'Phone',
                'enabled' => true,
                'width' => 15,
            ],
            'gender' => [
                'label' => 'Gender',
                'enabled' => true,
                'width' => 12,
            ],
            'date_of_birth' => [
                'label' => 'Date of Birth',
                'enabled' => true,
                'width' => 15,
                'format' => 'date',
            ],
            'branch_name' => [
                'label' => 'Branch',
                'enabled' => true,
                'width' => 20,
            ],
            'grade_label' => [
                'label' => 'Grade',
                'enabled' => true,
                'width' => 15,
            ],
            'section' => [
                'label' => 'Section',
                'enabled' => true,
                'width' => 12,
            ],
            'roll_number' => [
                'label' => 'Roll Number',
                'enabled' => true,
                'width' => 15,
            ],
            'academic_year' => [
                'label' => 'Academic Year',
                'enabled' => true,
                'width' => 15,
            ],
            'father_name' => [
                'label' => 'Father Name',
                'enabled' => true,
                'width' => 20,
            ],
            'father_phone' => [
                'label' => 'Father Phone',
                'enabled' => true,
                'width' => 15,
            ],
            'mother_name' => [
                'label' => 'Mother Name',
                'enabled' => true,
                'width' => 20,
            ],
            'mother_phone' => [
                'label' => 'Mother Phone',
                'enabled' => true,
                'width' => 15,
            ],
            'current_address' => [
                'label' => 'Address',
                'enabled' => false,
                'width' => 30,
            ],
            'city' => [
                'label' => 'City',
                'enabled' => true,
                'width' => 15,
            ],
            'state' => [
                'label' => 'State',
                'enabled' => true,
                'width' => 15,
            ],
            'pincode' => [
                'label' => 'Pincode',
                'enabled' => false,
                'width' => 12,
            ],
            'blood_group' => [
                'label' => 'Blood Group',
                'enabled' => false,
                'width' => 12,
            ],
            'student_status' => [
                'label' => 'Status',
                'enabled' => true,
                'width' => 12,
            ],
            'admission_date' => [
                'label' => 'Admission Date',
                'enabled' => true,
                'width' => 15,
                'format' => 'date',
            ],
            'is_active' => [
                'label' => 'Active',
                'enabled' => false,
                'width' => 10,
                'format' => 'boolean',
            ],
        ],
        'filename_prefix' => 'students',
        'sheet_name' => 'Students',
    ],

    /*
    |--------------------------------------------------------------------------
    | Student Attendance Export Configuration
    |--------------------------------------------------------------------------
    */
    
    'student_attendance' => [
        'columns' => [
            'id' => [
                'label' => 'ID',
                'enabled' => false,
                'width' => 10,
            ],
            'date' => [
                'label' => 'Date',
                'enabled' => true,
                'width' => 15,
                'format' => 'date',
            ],
            'admission_number' => [
                'label' => 'Admission No.',
                'enabled' => true,
                'width' => 18,
            ],
            'first_name' => [
                'label' => 'First Name',
                'enabled' => true,
                'width' => 18,
            ],
            'last_name' => [
                'label' => 'Last Name',
                'enabled' => true,
                'width' => 18,
            ],
            'email' => [
                'label' => 'Email',
                'enabled' => true,
                'width' => 25,
            ],
            'grade_label' => [
                'label' => 'Grade',
                'enabled' => true,
                'width' => 15,
            ],
            'section' => [
                'label' => 'Section',
                'enabled' => true,
                'width' => 12,
            ],
            'status' => [
                'label' => 'Status',
                'enabled' => true,
                'width' => 15,
            ],
            'remarks' => [
                'label' => 'Remarks',
                'enabled' => true,
                'width' => 30,
            ],
            'marked_by' => [
                'label' => 'Marked By',
                'enabled' => true,
                'width' => 20,
            ],
            'academic_year' => [
                'label' => 'Academic Year',
                'enabled' => false,
                'width' => 15,
            ],
            'created_at' => [
                'label' => 'Created At',
                'enabled' => false,
                'width' => 18,
                'format' => 'datetime',
            ],
        ],
        'filename_prefix' => 'student_attendance',
        'sheet_name' => 'Student Attendance',
    ],

    /*
    |--------------------------------------------------------------------------
    | Teacher Attendance Export Configuration
    |--------------------------------------------------------------------------
    */
    
    'teacher_attendance' => [
        'columns' => [
            'id' => [
                'label' => 'ID',
                'enabled' => false,
                'width' => 10,
            ],
            'date' => [
                'label' => 'Date',
                'enabled' => true,
                'width' => 15,
                'format' => 'date',
            ],
            'employee_id' => [
                'label' => 'Employee ID',
                'enabled' => true,
                'width' => 18,
            ],
            'first_name' => [
                'label' => 'First Name',
                'enabled' => true,
                'width' => 18,
            ],
            'last_name' => [
                'label' => 'Last Name',
                'enabled' => true,
                'width' => 18,
            ],
            'email' => [
                'label' => 'Email',
                'enabled' => true,
                'width' => 25,
            ],
            'status' => [
                'label' => 'Status',
                'enabled' => true,
                'width' => 15,
            ],
            'remarks' => [
                'label' => 'Remarks',
                'enabled' => true,
                'width' => 30,
            ],
            'created_at' => [
                'label' => 'Created At',
                'enabled' => false,
                'width' => 18,
                'format' => 'datetime',
            ],
        ],
        'filename_prefix' => 'teacher_attendance',
        'sheet_name' => 'Teacher Attendance',
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Export Settings
    |--------------------------------------------------------------------------
    */

    'global' => [
        'date_format' => 'd-m-Y',
        'datetime_format' => 'd-m-Y H:i:s',
        'excel_writer_type' => 'Xlsx', // Excel format type
        'csv_delimiter' => ',',
        'include_timestamp' => true,
    ],
];

