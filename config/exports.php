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
                'enabled' => false,  // Disabled to reduce PDF width
                'width' => 12,
            ],
            'date_of_birth' => [
                'label' => 'Date of Birth',
                'enabled' => false,  // Disabled to reduce PDF width
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
                'enabled' => false,  // Disabled to reduce PDF width
                'width' => 15,
            ],
            'mother_name' => [
                'label' => 'Mother Name',
                'enabled' => false,  // Disabled to reduce PDF width
                'width' => 20,
            ],
            'mother_phone' => [
                'label' => 'Mother Phone',
                'enabled' => false,  // Disabled to reduce PDF width
                'width' => 15,
            ],
            'current_address' => [
                'label' => 'Address',
                'enabled' => false,
                'width' => 30,
            ],
            'city' => [
                'label' => 'City',
                'enabled' => false,  // Disabled to reduce PDF width
                'width' => 15,
            ],
            'state' => [
                'label' => 'State',
                'enabled' => false,  // Disabled to reduce PDF width
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
                'enabled' => false,  // Disabled to reduce PDF width
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
                'enabled' => false,  // Disabled to reduce PDF width
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
                'enabled' => false,  // Disabled to reduce PDF width
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
    | Teachers Export Configuration
    |--------------------------------------------------------------------------
    */
    
    'teachers' => [
        'columns' => [
            'id' => [
                'label' => 'ID',
                'enabled' => false,
                'width' => 10,
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
            'phone' => [
                'label' => 'Phone',
                'enabled' => true,
                'width' => 15,
            ],
            'category_type' => [
                'label' => 'Category',
                'enabled' => true,
                'width' => 15,
            ],
            'designation' => [
                'label' => 'Designation',
                'enabled' => true,
                'width' => 18,
            ],
            'department_name' => [
                'label' => 'Department',
                'enabled' => true,
                'width' => 18,
            ],
            'branch_name' => [
                'label' => 'Branch',
                'enabled' => true,
                'width' => 18,
            ],
            'gender' => [
                'label' => 'Gender',
                'enabled' => false,  // Disabled to reduce PDF width
                'width' => 12,
            ],
            'date_of_birth' => [
                'label' => 'Date of Birth',
                'enabled' => false,  // Disabled to reduce PDF width
                'width' => 15,
                'format' => 'date',
            ],
            'joining_date' => [
                'label' => 'Joining Date',
                'enabled' => true,
                'width' => 15,
                'format' => 'date',
            ],
            'employee_type' => [
                'label' => 'Employee Type',
                'enabled' => false,  // Disabled to reduce PDF width
                'width' => 15,
            ],
            'blood_group' => [
                'label' => 'Blood Group',
                'enabled' => false,
                'width' => 12,
            ],
            'pan_number' => [
                'label' => 'PAN Number',
                'enabled' => false,
                'width' => 15,
            ],
            'aadhaar_number' => [
                'label' => 'Aadhaar Number',
                'enabled' => false,
                'width' => 18,
            ],
            'basic_salary' => [
                'label' => 'Basic Salary',
                'enabled' => false,
                'width' => 15,
            ],
            'current_address' => [
                'label' => 'Address',
                'enabled' => false,
                'width' => 30,
            ],
            'current_city' => [
                'label' => 'City',
                'enabled' => false,  // Disabled to reduce PDF width
                'width' => 15,
            ],
            'current_state' => [
                'label' => 'State',
                'enabled' => false,  // Disabled to reduce PDF width
                'width' => 15,
            ],
            'current_pincode' => [
                'label' => 'Pincode',
                'enabled' => false,
                'width' => 12,
            ],
            'emergency_contact_name' => [
                'label' => 'Emergency Contact Name',
                'enabled' => false,
                'width' => 20,
            ],
            'emergency_contact_phone' => [
                'label' => 'Emergency Contact Phone',
                'enabled' => false,
                'width' => 18,
            ],
            'teacher_status' => [
                'label' => 'Status',
                'enabled' => true,
                'width' => 12,
            ],
            'is_active' => [
                'label' => 'Active',
                'enabled' => false,
                'width' => 10,
                'format' => 'boolean',
            ],
            'created_at' => [
                'label' => 'Created At',
                'enabled' => false,
                'width' => 18,
                'format' => 'datetime',
            ],
        ],
        'filename_prefix' => 'teachers',
        'sheet_name' => 'Teachers',
    ],

    /*
    |--------------------------------------------------------------------------
    | Branches Export Configuration
    |--------------------------------------------------------------------------
    */
    
    'branches' => [
        'columns' => [
            'id' => [
                'label' => 'ID',
                'enabled' => false,
                'width' => 10,
            ],
            'code' => [
                'label' => 'Branch Code',
                'enabled' => true,
                'width' => 15,
            ],
            'name' => [
                'label' => 'Branch Name',
                'enabled' => true,
                'width' => 25,
            ],
            'branch_type' => [
                'label' => 'Type',
                'enabled' => true,
                'width' => 15,
            ],
            'parent_branch_name' => [
                'label' => 'Parent Branch',
                'enabled' => true,
                'width' => 20,
            ],
            'city' => [
                'label' => 'City',
                'enabled' => true,
                'width' => 18,
            ],
            'state' => [
                'label' => 'State',
                'enabled' => true,
                'width' => 18,
            ],
            'region' => [
                'label' => 'Region',
                'enabled' => true,
                'width' => 15,
            ],
            'phone' => [
                'label' => 'Phone',
                'enabled' => true,
                'width' => 15,
            ],
            'email' => [
                'label' => 'Email',
                'enabled' => true,
                'width' => 25,
            ],
            'principal_name' => [
                'label' => 'Principal',
                'enabled' => true,
                'width' => 20,
            ],
            'established_date' => [
                'label' => 'Established Date',
                'enabled' => false,
                'width' => 15,
                'format' => 'date',
            ],
            'total_capacity' => [
                'label' => 'Capacity',
                'enabled' => false,
                'width' => 12,
            ],
            'current_enrollment' => [
                'label' => 'Enrollment',
                'enabled' => false,
                'width' => 12,
            ],
            'address' => [
                'label' => 'Address',
                'enabled' => false,
                'width' => 30,
            ],
            'country' => [
                'label' => 'Country',
                'enabled' => false,
                'width' => 15,
            ],
            'pincode' => [
                'label' => 'Pincode',
                'enabled' => false,
                'width' => 12,
            ],
            'board' => [
                'label' => 'Board',
                'enabled' => false,
                'width' => 15,
            ],
            'affiliation_number' => [
                'label' => 'Affiliation No.',
                'enabled' => false,
                'width' => 18,
            ],
            'is_main_branch' => [
                'label' => 'Main Branch',
                'enabled' => false,
                'width' => 12,
                'format' => 'boolean',
            ],
            'status' => [
                'label' => 'Status',
                'enabled' => true,
                'width' => 12,
            ],
            'is_active' => [
                'label' => 'Active',
                'enabled' => false,
                'width' => 10,
                'format' => 'boolean',
            ],
            'created_at' => [
                'label' => 'Created At',
                'enabled' => false,
                'width' => 18,
                'format' => 'datetime',
            ],
        ],
        'filename_prefix' => 'branches',
        'sheet_name' => 'Branches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Grades Export Configuration
    |--------------------------------------------------------------------------
    */
    
    'grades' => [
        'columns' => [
            'value' => [
                'label' => 'Grade Value',
                'enabled' => true,
                'width' => 15,
            ],
            'label' => [
                'label' => 'Grade Label',
                'enabled' => true,
                'width' => 20,
            ],
            'description' => [
                'label' => 'Description',
                'enabled' => true,
                'width' => 35,
            ],
            'is_active' => [
                'label' => 'Active',
                'enabled' => true,
                'width' => 12,
                'format' => 'boolean',
            ],
            'created_at' => [
                'label' => 'Created At',
                'enabled' => false,
                'width' => 18,
                'format' => 'datetime',
            ],
            'updated_at' => [
                'label' => 'Updated At',
                'enabled' => false,
                'width' => 18,
                'format' => 'datetime',
            ],
        ],
        'filename_prefix' => 'grades',
        'sheet_name' => 'Grades',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sections Export Configuration
    |--------------------------------------------------------------------------
    */
    
    'sections' => [
        'columns' => [
            'id' => [
                'label' => 'ID',
                'enabled' => false,
                'width' => 10,
            ],
            'code' => [
                'label' => 'Section Code',
                'enabled' => true,
                'width' => 15,
            ],
            'name' => [
                'label' => 'Section Name',
                'enabled' => true,
                'width' => 18,
            ],
            'grade_label' => [
                'label' => 'Grade',
                'enabled' => true,
                'width' => 18,
            ],
            'branch_name' => [
                'label' => 'Branch',
                'enabled' => true,
                'width' => 20,
            ],
            'class_teacher_name' => [
                'label' => 'Class Teacher',
                'enabled' => true,
                'width' => 22,
            ],
            'capacity' => [
                'label' => 'Capacity',
                'enabled' => true,
                'width' => 12,
            ],
            'current_strength' => [
                'label' => 'Current Strength',
                'enabled' => true,
                'width' => 15,
            ],
            'actual_strength' => [
                'label' => 'Actual Strength',
                'enabled' => false,
                'width' => 15,
            ],
            'room_number' => [
                'label' => 'Room Number',
                'enabled' => true,
                'width' => 15,
            ],
            'description' => [
                'label' => 'Description',
                'enabled' => false,
                'width' => 30,
            ],
            'is_active' => [
                'label' => 'Active',
                'enabled' => true,
                'width' => 10,
                'format' => 'boolean',
            ],
            'created_at' => [
                'label' => 'Created At',
                'enabled' => false,
                'width' => 18,
                'format' => 'datetime',
            ],
        ],
        'filename_prefix' => 'sections',
        'sheet_name' => 'Sections',
    ],

    /*
    |--------------------------------------------------------------------------
    | Income Transactions Export Configuration
    |--------------------------------------------------------------------------
    */
    
    'income_transactions' => [
        'columns' => [
            'id' => [
                'label' => 'ID',
                'enabled' => false,
                'width' => 10,
            ],
            'transaction_number' => [
                'label' => 'Transaction #',
                'enabled' => true,
                'width' => 20,
            ],
            'transaction_date' => [
                'label' => 'Date',
                'enabled' => true,
                'width' => 15,
                'format' => 'date',
            ],
            'category_name' => [
                'label' => 'Category',
                'enabled' => true,
                'width' => 20,
            ],
            'description' => [
                'label' => 'Description',
                'enabled' => true,
                'width' => 30,
            ],
            'party_name' => [
                'label' => 'From Party',
                'enabled' => true,
                'width' => 20,
            ],
            'party_type' => [
                'label' => 'Party Type',
                'enabled' => false,
                'width' => 15,
            ],
            'amount' => [
                'label' => 'Amount',
                'enabled' => true,
                'width' => 15,
            ],
            'payment_method' => [
                'label' => 'Payment Method',
                'enabled' => true,
                'width' => 18,
            ],
            'payment_reference' => [
                'label' => 'Reference',
                'enabled' => false,
                'width' => 20,
            ],
            'bank_name' => [
                'label' => 'Bank',
                'enabled' => false,
                'width' => 18,
            ],
            'branch_name' => [
                'label' => 'Branch',
                'enabled' => true,
                'width' => 18,
            ],
            'status' => [
                'label' => 'Status',
                'enabled' => true,
                'width' => 12,
            ],
            'financial_year' => [
                'label' => 'Financial Year',
                'enabled' => false,
                'width' => 15,
            ],
            'month' => [
                'label' => 'Month',
                'enabled' => false,
                'width' => 12,
            ],
            'created_by_name' => [
                'label' => 'Created By',
                'enabled' => false,
                'width' => 18,
            ],
            'approved_by_name' => [
                'label' => 'Approved By',
                'enabled' => false,
                'width' => 18,
            ],
            'approved_at' => [
                'label' => 'Approved At',
                'enabled' => false,
                'width' => 18,
                'format' => 'datetime',
            ],
            'created_at' => [
                'label' => 'Created At',
                'enabled' => false,
                'width' => 18,
                'format' => 'datetime',
            ],
        ],
        'filename_prefix' => 'income_transactions',
        'sheet_name' => 'Income Transactions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Expense Transactions Export Configuration
    |--------------------------------------------------------------------------
    */
    
    'expense_transactions' => [
        'columns' => [
            'id' => [
                'label' => 'ID',
                'enabled' => false,
                'width' => 10,
            ],
            'transaction_number' => [
                'label' => 'Transaction #',
                'enabled' => true,
                'width' => 20,
            ],
            'transaction_date' => [
                'label' => 'Date',
                'enabled' => true,
                'width' => 15,
                'format' => 'date',
            ],
            'category_name' => [
                'label' => 'Category',
                'enabled' => true,
                'width' => 20,
            ],
            'description' => [
                'label' => 'Description',
                'enabled' => true,
                'width' => 30,
            ],
            'party_name' => [
                'label' => 'To Party',
                'enabled' => true,
                'width' => 20,
            ],
            'party_type' => [
                'label' => 'Party Type',
                'enabled' => false,
                'width' => 15,
            ],
            'amount' => [
                'label' => 'Amount',
                'enabled' => true,
                'width' => 15,
            ],
            'payment_method' => [
                'label' => 'Payment Method',
                'enabled' => true,
                'width' => 18,
            ],
            'payment_reference' => [
                'label' => 'Reference',
                'enabled' => false,
                'width' => 20,
            ],
            'bank_name' => [
                'label' => 'Bank',
                'enabled' => false,
                'width' => 18,
            ],
            'branch_name' => [
                'label' => 'Branch',
                'enabled' => true,
                'width' => 18,
            ],
            'status' => [
                'label' => 'Status',
                'enabled' => true,
                'width' => 12,
            ],
            'financial_year' => [
                'label' => 'Financial Year',
                'enabled' => false,
                'width' => 15,
            ],
            'month' => [
                'label' => 'Month',
                'enabled' => false,
                'width' => 12,
            ],
            'created_by_name' => [
                'label' => 'Created By',
                'enabled' => false,
                'width' => 18,
            ],
            'approved_by_name' => [
                'label' => 'Approved By',
                'enabled' => false,
                'width' => 18,
            ],
            'approved_at' => [
                'label' => 'Approved At',
                'enabled' => false,
                'width' => 18,
                'format' => 'datetime',
            ],
            'created_at' => [
                'label' => 'Created At',
                'enabled' => false,
                'width' => 18,
                'format' => 'datetime',
            ],
        ],
        'filename_prefix' => 'expense_transactions',
        'sheet_name' => 'Expense Transactions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Holidays Export Configuration
    |--------------------------------------------------------------------------
    */
    
    'holidays' => [
        'columns' => [
            'id' => [
                'label' => 'ID',
                'enabled' => false,
                'width' => 10,
            ],
            'title' => [
                'label' => 'Holiday Title',
                'enabled' => true,
                'width' => 30,
            ],
            'start_date' => [
                'label' => 'Start Date',
                'enabled' => true,
                'width' => 15,
                'format' => 'date',
            ],
            'end_date' => [
                'label' => 'End Date',
                'enabled' => true,
                'width' => 15,
                'format' => 'date',
            ],
            'duration' => [
                'label' => 'Duration (Days)',
                'enabled' => true,
                'width' => 15,
            ],
            'type' => [
                'label' => 'Type',
                'enabled' => true,
                'width' => 15,
            ],
            'branch_name' => [
                'label' => 'Branch',
                'enabled' => true,
                'width' => 20,
            ],
            'description' => [
                'label' => 'Description',
                'enabled' => true,
                'width' => 35,
            ],
            'academic_year' => [
                'label' => 'Academic Year',
                'enabled' => true,
                'width' => 15,
            ],
            'is_recurring' => [
                'label' => 'Recurring',
                'enabled' => false,
                'width' => 12,
                'format' => 'boolean',
            ],
            'color' => [
                'label' => 'Color',
                'enabled' => false,
                'width' => 12,
            ],
            'is_active' => [
                'label' => 'Active',
                'enabled' => false,
                'width' => 10,
                'format' => 'boolean',
            ],
            'created_by_name' => [
                'label' => 'Created By',
                'enabled' => false,
                'width' => 18,
            ],
            'created_at' => [
                'label' => 'Created At',
                'enabled' => false,
                'width' => 18,
                'format' => 'datetime',
            ],
        ],
        'filename_prefix' => 'holidays',
        'sheet_name' => 'Holidays',
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

