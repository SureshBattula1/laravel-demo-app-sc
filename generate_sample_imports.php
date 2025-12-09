<?php

/**
 * Generate Sample Import Files
 * 
 * This script generates proper Excel files for testing the import module.
 * Run: php generate_sample_imports.php
 */

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

// Create output directory
$outputDir = __DIR__ . '/public';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Generate Student Import File
generateStudentImport($outputDir);

// Generate Teacher Import File
generateTeacherImport($outputDir);

echo "✅ Sample import files generated successfully!\n";
echo "📁 Location: {$outputDir}/\n";
echo "   - sample_student_import.xlsx\n";
echo "   - sample_teacher_import.xlsx\n";

function generateStudentImport($outputDir) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Student Import');

    // Headers
    $headers = [
        'First Name', 'Last Name', 'Email', 'Phone',
        'Admission Number', 'Admission Date', 'Roll Number', 'Registration Number',
        'Date of Birth', 'Gender', 'Blood Group', 'Religion', 'Category',
        'Nationality', 'Mother Tongue',
        'Current Address', 'Permanent Address', 'City', 'State', 'Country', 'Pincode',
        'Father Name', 'Father Phone', 'Father Email', 'Father Occupation', 'Father Annual Income',
        'Mother Name', 'Mother Phone', 'Mother Email', 'Mother Occupation', 'Mother Annual Income',
        'Guardian Name', 'Guardian Relation', 'Guardian Phone',
        'Emergency Contact Name', 'Emergency Contact Phone', 'Emergency Contact Relation',
        'Previous School', 'Previous Grade', 'Previous Percentage', 'Transfer Certificate Number',
        'Medical History', 'Allergies', 'Medications', 'Height (cm)', 'Weight (kg)',
        'Password', 'Remarks'
    ];

    // Write headers
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }

    // Style headers
    $lastCol = $col;
    $headerRange = 'A1:' . chr(ord($lastCol) - 1) . '1';
    $sheet->getStyle($headerRange)->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);

    // Sample data - 10 students
    $students = [
        ['Rajesh', 'Kumar', 'rajesh.kumar@example.com', '9876543210', 'STU-2024-001', '2024-04-15', '101', 'REG-2024-001', '2010-05-20', 'Male', 'A+', 'Hindu', 'General', 'Indian', 'English', '123 Main Street Mumbai', '123 Main Street Mumbai', 'Mumbai', 'Maharashtra', 'India', '400001', 'Ramesh Kumar', '9876543211', 'ramesh.kumar@example.com', 'Engineer', '500000', 'Sunita Kumar', '9876543212', 'sunita.kumar@example.com', 'Teacher', '300000', '', '', '', 'Rajesh Kumar', '9876543210', 'Father', 'ABC School', '4', '85.5', 'TC-001', 'No major issues', 'None', 'None', '150', '45', 'Welcome@123', 'Good student'],
        ['Priya', 'Sharma', 'priya.sharma@example.com', '9876543213', 'STU-2024-002', '2024-04-16', '102', 'REG-2024-002', '2010-08-15', 'Female', 'B+', 'Hindu', 'General', 'Indian', 'Hindi', '456 Park Avenue Delhi', '456 Park Avenue Delhi', 'Delhi', 'Delhi', 'India', '110001', 'Mahesh Sharma', '9876543214', 'mahesh.sharma@example.com', 'Doctor', '800000', 'Kavita Sharma', '9876543215', 'kavita.sharma@example.com', 'Housewife', '0', '', '', '', 'Mahesh Sharma', '9876543214', 'Father', 'XYZ School', '5', '90.2', 'TC-002', 'No issues', 'None', 'None', '145', '42', 'Welcome@123', 'Excellent student'],
        ['Amit', 'Patel', 'amit.patel@example.com', '9876543216', 'STU-2024-003', '2024-04-17', '103', 'REG-2024-003', '2011-03-10', 'Male', 'O+', 'Hindu', 'General', 'Indian', 'Gujarati', '789 Gandhi Road Ahmedabad', '789 Gandhi Road Ahmedabad', 'Ahmedabad', 'Gujarat', 'India', '380001', 'Sanjay Patel', '9876543217', 'sanjay.patel@example.com', 'Businessman', '600000', 'Meera Patel', '9876543218', 'meera.patel@example.com', 'Teacher', '250000', '', '', '', 'Sanjay Patel', '9876543217', 'Father', 'DEF School', '3', '88.0', 'TC-003', 'No issues', 'None', 'None', '148', '48', 'Welcome@123', 'Active student'],
        ['Sneha', 'Reddy', 'sneha.reddy@example.com', '9876543219', 'STU-2024-004', '2024-04-18', '104', 'REG-2024-004', '2010-11-25', 'Female', 'AB+', 'Hindu', 'General', 'Indian', 'Telugu', '321 MG Road Bangalore', '321 MG Road Bangalore', 'Bangalore', 'Karnataka', 'India', '560001', 'Rajesh Reddy', '9876543220', 'rajesh.reddy@example.com', 'Software Engineer', '700000', 'Lakshmi Reddy', '9876543221', 'lakshmi.reddy@example.com', 'Doctor', '500000', '', '', '', 'Rajesh Reddy', '9876543220', 'Father', 'GHI School', '4', '92.5', 'TC-004', 'No issues', 'None', 'None', '142', '40', 'Welcome@123', 'Brilliant student'],
        ['Vikram', 'Singh', 'vikram.singh@example.com', '9876543222', 'STU-2024-005', '2024-04-19', '105', 'REG-2024-005', '2011-01-12', 'Male', 'A-', 'Sikh', 'General', 'Indian', 'Punjabi', '654 Sector 15 Chandigarh', '654 Sector 15 Chandigarh', 'Chandigarh', 'Punjab', 'India', '160015', 'Harpreet Singh', '9876543223', 'harpreet.singh@example.com', 'Government Officer', '550000', 'Manpreet Kaur', '9876543224', 'manpreet.kaur@example.com', 'Teacher', '300000', '', '', '', 'Harpreet Singh', '9876543223', 'Father', 'JKL School', '3', '87.0', 'TC-005', 'No issues', 'None', 'None', '152', '50', 'Welcome@123', 'Hardworking student'],
        ['Ananya', 'Das', 'ananya.das@example.com', '9876543225', 'STU-2024-006', '2024-04-20', '106', 'REG-2024-006', '2010-09-08', 'Female', 'B-', 'Hindu', 'General', 'Indian', 'Bengali', '147 Park Street Kolkata', '147 Park Street Kolkata', 'Kolkata', 'West Bengal', 'India', '700016', 'Subhash Das', '9876543226', 'subhash.das@example.com', 'Accountant', '450000', 'Anita Das', '9876543227', 'anita.das@example.com', 'Housewife', '0', '', '', '', 'Subhash Das', '9876543226', 'Father', 'MNO School', '4', '89.5', 'TC-006', 'No issues', 'None', 'None', '140', '38', 'Welcome@123', 'Creative student'],
        ['Rahul', 'Joshi', 'rahul.joshi@example.com', '9876543228', 'STU-2024-007', '2024-04-21', '107', 'REG-2024-007', '2011-06-18', 'Male', 'O-', 'Hindu', 'General', 'Indian', 'Marathi', '258 FC Road Pune', '258 FC Road Pune', 'Pune', 'Maharashtra', 'India', '411004', 'Sunil Joshi', '9876543229', 'sunil.joshi@example.com', 'Teacher', '400000', 'Shraddha Joshi', '9876543230', 'shraddha.joshi@example.com', 'Teacher', '350000', '', '', '', 'Sunil Joshi', '9876543229', 'Father', 'PQR School', '3', '86.5', 'TC-007', 'No issues', 'None', 'None', '151', '47', 'Welcome@123', 'Disciplined student'],
        ['Isha', 'Mehta', 'isha.mehta@example.com', '9876543231', 'STU-2024-008', '2024-04-22', '108', 'REG-2024-008', '2010-12-30', 'Female', 'A+', 'Hindu', 'General', 'Indian', 'Gujarati', '369 CG Road Ahmedabad', '369 CG Road Ahmedabad', 'Ahmedabad', 'Gujarat', 'India', '380009', 'Vikram Mehta', '9876543232', 'vikram.mehta@example.com', 'CA', '650000', 'Neha Mehta', '9876543233', 'neha.mehta@example.com', 'CA', '600000', '', '', '', 'Vikram Mehta', '9876543232', 'Father', 'STU School', '4', '91.0', 'TC-008', 'No issues', 'None', 'None', '143', '41', 'Welcome@123', 'Intelligent student'],
        ['Arjun', 'Nair', 'arjun.nair@example.com', '9876543234', 'STU-2024-009', '2024-04-23', '109', 'REG-2024-009', '2011-04-22', 'Male', 'B+', 'Hindu', 'General', 'Indian', 'Malayalam', '741 MG Road Kochi', '741 MG Road Kochi', 'Kochi', 'Kerala', 'India', '682001', 'Suresh Nair', '9876543235', 'suresh.nair@example.com', 'Engineer', '600000', 'Radha Nair', '9876543236', 'radha.nair@example.com', 'Teacher', '300000', '', '', '', 'Suresh Nair', '9876543235', 'Father', 'VWX School', '3', '88.5', 'TC-009', 'No issues', 'None', 'None', '149', '46', 'Welcome@123', 'Well behaved student'],
        ['Kavya', 'Iyer', 'kavya.iyer@example.com', '9876543237', 'STU-2024-010', '2024-04-24', '110', 'REG-2024-010', '2010-10-05', 'Female', 'AB-', 'Hindu', 'General', 'Indian', 'Tamil', '852 Anna Salai Chennai', '852 Anna Salai Chennai', 'Chennai', 'Tamil Nadu', 'India', '600002', 'Ramesh Iyer', '9876543238', 'ramesh.iyer@example.com', 'Doctor', '750000', 'Padma Iyer', '9876543239', 'padma.iyer@example.com', 'Doctor', '700000', '', '', '', 'Ramesh Iyer', '9876543238', 'Father', 'YZA School', '4', '93.0', 'TC-010', 'No issues', 'None', 'None', '141', '39', 'Welcome@123', 'Outstanding student'],
    ];

    // Write data
    $row = 2;
    foreach ($students as $student) {
        $col = 'A';
        foreach ($student as $value) {
            $sheet->setCellValue($col . $row, $value);
            $col++;
        }
        $row++;
    }

    // Auto-size columns
    foreach (range('A', chr(ord($lastCol) - 1)) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Freeze header row
    $sheet->freezePane('A2');

    // Save file
    $writer = new Xlsx($spreadsheet);
    $writer->save($outputDir . '/sample_student_import.xlsx');
}

function generateTeacherImport($outputDir) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Teacher Import');

    // Headers
    $headers = [
        'First Name', 'Last Name', 'Email', 'Phone',
        'Employee ID', 'Joining Date', 'Leaving Date', 'Designation', 'Employee Type',
        'Qualification', 'Experience Years', 'Specialization', 'Registration Number',
        'Subjects', 'Classes Assigned',
        'Is Class Teacher', 'Class Teacher of Grade', 'Class Teacher of Section',
        'Date of Birth', 'Gender', 'Blood Group', 'Religion', 'Nationality',
        'Current Address', 'Permanent Address', 'City', 'State', 'Pincode',
        'Emergency Contact Name', 'Emergency Contact Phone', 'Emergency Contact Relation',
        'Salary Grade', 'Basic Salary',
        'Bank Name', 'Bank Account Number', 'Bank IFSC Code',
        'PAN Number', 'Aadhar Number',
        'Password', 'Remarks'
    ];

    // Write headers
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }

    // Style headers
    $lastCol = $col;
    $headerRange = 'A1:' . chr(ord($lastCol) - 1) . '1';
    $sheet->getStyle($headerRange)->applyFromArray([
        'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);

    // Sample data - 10 teachers
    $teachers = [
        ['Ravi', 'Krishnan', 'ravi.krishnan@example.com', '9876543301', 'TCH-2024-001', '2024-01-01', '', 'Senior Teacher', 'Permanent', 'M.Sc Mathematics', '8', 'Mathematics', 'REG-TCH-001', 'Mathematics, Physics', 'Class 9, Class 10', 'Yes', '9', 'A', '1985-03-15', 'Male', 'A+', 'Hindu', 'Indian', '456 Park Avenue Delhi', '456 Park Avenue Delhi', 'Delhi', 'Delhi', '110001', 'Shanti Krishnan', '9876543302', 'Wife', 'Grade A', '50000', 'State Bank of India', '1234567890123456', 'SBIN0001234', 'ABCDE1234F', '123456789012', 'Welcome@123', 'Excellent teacher with strong subject knowledge'],
        ['Meera', 'Sharma', 'meera.sharma@example.com', '9876543303', 'TCH-2024-002', '2024-01-15', '', 'Teacher', 'Permanent', 'M.A English', '5', 'English Literature', 'REG-TCH-002', 'English', 'Class 6, Class 7', 'Yes', '6', 'B', '1988-07-20', 'Female', 'B+', 'Hindu', 'Indian', '789 MG Road Bangalore', '789 MG Road Bangalore', 'Bangalore', 'Karnataka', '560001', 'Rajesh Sharma', '9876543304', 'Husband', 'Grade B', '45000', 'HDFC Bank', '2345678901234567', 'HDFC0001234', 'BCDEF2345G', '234567890123', 'Welcome@123', 'Passionate about literature and teaching'],
        ['Anil', 'Kumar', 'anil.kumar@example.com', '9876543305', 'TCH-2024-003', '2024-02-01', '', 'Senior Teacher', 'Permanent', 'Ph.D Physics', '12', 'Physics', 'REG-TCH-003', 'Physics, Chemistry', 'Class 11, Class 12', 'No', '', '', '1980-11-10', 'Male', 'O+', 'Hindu', 'Indian', '321 Sector 15 Chandigarh', '321 Sector 15 Chandigarh', 'Chandigarh', 'Punjab', '160015', 'Sunita Kumar', '9876543306', 'Wife', 'Grade A', '55000', 'ICICI Bank', '3456789012345678', 'ICIC0001234', 'CDEFG3456H', '345678901234', 'Welcome@123', 'Expert in Physics with research background'],
        ['Priya', 'Patel', 'priya.patel@example.com', '9876543307', 'TCH-2024-004', '2024-02-15', '', 'Teacher', 'Permanent', 'B.Ed Chemistry', '4', 'Chemistry', 'REG-TCH-004', 'Chemistry', 'Class 8, Class 9', 'No', '', '', '1990-04-25', 'Female', 'A-', 'Hindu', 'Indian', '147 CG Road Ahmedabad', '147 CG Road Ahmedabad', 'Ahmedabad', 'Gujarat', '380009', 'Vikram Patel', '9876543308', 'Husband', 'Grade B', '42000', 'Axis Bank', '4567890123456789', 'UTIB0001234', 'DEFGH4567I', '456789012345', 'Welcome@123', 'Young and enthusiastic chemistry teacher'],
        ['Suresh', 'Reddy', 'suresh.reddy@example.com', '9876543309', 'TCH-2024-005', '2024-03-01', '', 'Teacher', 'Permanent', 'M.Sc Biology', '6', 'Biology', 'REG-TCH-005', 'Biology', 'Class 10, Class 11', 'Yes', '10', 'A', '1987-09-12', 'Male', 'B-', 'Hindu', 'Indian', '258 Park Street Kolkata', '258 Park Street Kolkata', 'Kolkata', 'West Bengal', '700016', 'Lakshmi Reddy', '9876543310', 'Wife', 'Grade B', '48000', 'Punjab National Bank', '5678901234567890', 'PUNB0001234', 'EFGHI5678J', '567890123456', 'Welcome@123', 'Experienced biology teacher'],
        ['Kavita', 'Singh', 'kavita.singh@example.com', '9876543311', 'TCH-2024-006', '2024-03-15', '', 'Senior Teacher', 'Permanent', 'Ph.D History', '10', 'History', 'REG-TCH-006', 'History, Social Studies', 'Class 7, Class 8', 'Yes', '7', 'C', '1983-12-05', 'Female', 'AB+', 'Hindu', 'Indian', '369 FC Road Pune', '369 FC Road Pune', 'Pune', 'Maharashtra', '411004', 'Harpreet Singh', '9876543312', 'Husband', 'Grade A', '52000', 'Union Bank of India', '6789012345678901', 'UBIN0001234', 'FGHIJ6789K', '678901234567', 'Welcome@123', 'Well versed in history and social studies'],
        ['Rajesh', 'Joshi', 'rajesh.joshi@example.com', '9876543313', 'TCH-2024-007', '2024-04-01', '', 'Teacher', 'Permanent', 'B.Sc Computer Science', '3', 'Computer Science', 'REG-TCH-007', 'Computer Science', 'Class 9, Class 10', 'No', '', '', '1992-08-18', 'Male', 'O-', 'Hindu', 'Indian', '741 Anna Salai Chennai', '741 Anna Salai Chennai', 'Chennai', 'Tamil Nadu', '600002', 'Anita Joshi', '9876543314', 'Wife', 'Grade C', '40000', 'Canara Bank', '7890123456789012', 'CNRB0001234', 'GHIJK7890L', '789012345678', 'Welcome@123', 'Tech-savvy computer science teacher'],
        ['Ananya', 'Das', 'ananya.das@example.com', '9876543315', 'TCH-2024-008', '2024-04-15', '', 'Teacher', 'Permanent', 'M.A Geography', '5', 'Geography', 'REG-TCH-008', 'Geography', 'Class 6, Class 7', 'No', '', '', '1989-02-28', 'Female', 'A+', 'Hindu', 'Indian', '852 MG Road Kochi', '852 MG Road Kochi', 'Kochi', 'Kerala', '682001', 'Subhash Das', '9876543316', 'Husband', 'Grade B', '44000', 'Indian Bank', '8901234567890123', 'IDIB0001234', 'HIJKL8901M', '890123456789', 'Welcome@123', 'Geography expert with field experience'],
        ['Vikram', 'Mehta', 'vikram.mehta@example.com', '9876543317', 'TCH-2024-009', '2024-05-01', '', 'Senior Teacher', 'Permanent', 'Ph.D Economics', '11', 'Economics', 'REG-TCH-009', 'Economics', 'Class 11, Class 12', 'No', '', '', '1981-06-15', 'Male', 'B+', 'Hindu', 'Indian', '963 CG Road Ahmedabad', '963 CG Road Ahmedabad', 'Ahmedabad', 'Gujarat', '380009', 'Neha Mehta', '9876543318', 'Wife', 'Grade A', '53000', 'Bank of Baroda', '9012345678901234', 'BARB0001234', 'JKLMN9012N', '901234567890', 'Welcome@123', 'Economics professor with industry experience'],
        ['Sneha', 'Nair', 'sneha.nair@example.com', '9876543319', 'TCH-2024-010', '2024-05-15', '', 'Teacher', 'Permanent', 'B.Ed Hindi', '4', 'Hindi', 'REG-TCH-010', 'Hindi', 'Class 5, Class 6', 'Yes', '5', 'A', '1991-10-22', 'Female', 'AB-', 'Hindu', 'Indian', '159 Sector 20 Chandigarh', '159 Sector 20 Chandigarh', 'Chandigarh', 'Punjab', '160020', 'Suresh Nair', '9876543320', 'Husband', 'Grade B', '41000', 'Oriental Bank of Commerce', '0123456789012345', 'ORBC0001234', 'KLMNO0123O', '012345678901', 'Welcome@123', 'Passionate Hindi teacher'],
    ];

    // Write data
    $row = 2;
    foreach ($teachers as $teacher) {
        $col = 'A';
        foreach ($teacher as $value) {
            $sheet->setCellValue($col . $row, $value);
            $col++;
        }
        $row++;
    }

    // Auto-size columns
    for ($i = 1; $i <= count($headers); $i++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
        $sheet->getColumnDimension($colLetter)->setAutoSize(true);
    }

    // Freeze header row
    $sheet->freezePane('A2');

    // Save file
    $writer = new Xlsx($spreadsheet);
    $writer->save($outputDir . '/sample_teacher_import.xlsx');
}

