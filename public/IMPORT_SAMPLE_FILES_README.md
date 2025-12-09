# Sample Import Files

This directory contains sample CSV files for testing the import module.

## Files Available

1. **sample_student_import.csv** - Contains 10 sample student records
2. **sample_teacher_import.csv** - Contains 10 sample teacher records

## How to Use

### Option 1: Use CSV Files Directly
1. Navigate to the Import module in the application
2. Select "Students" or "Teachers" import
3. Click "Upload File"
4. Select the corresponding CSV file:
   - For students: `sample_student_import.csv`
   - For teachers: `sample_teacher_import.csv`
5. Fill in the required context fields (Branch, Grade, Section, Academic Year for students)
6. Upload and proceed with validation

### Option 2: Convert to Excel Format
1. Open the CSV file in Microsoft Excel or Google Sheets
2. Save As → Excel Workbook (.xlsx)
3. Use the Excel file for import

## File Locations

The files are located in:
- `public/sample_student_import.csv`
- `public/sample_teacher_import.csv`

You can access them directly via:
- `http://your-domain/sample_student_import.csv`
- `http://your-domain/sample_teacher_import.csv`

## Sample Data Details

### Student File Contains:
- 10 student records with complete information
- All required fields: First Name, Last Name, Email, Phone, Admission Number, etc.
- Parent/Guardian information
- Medical and previous school details
- Default password: `Welcome@123` (for all students)

### Teacher File Contains:
- 10 teacher records with complete information
- All required fields: First Name, Last Name, Email, Phone, Employee ID, etc.
- Professional details: Qualification, Experience, Specialization
- Salary and bank details
- Default password: `Welcome@123` (for all teachers)

## Important Notes

1. **Email Addresses**: All email addresses are example.com addresses. Make sure to use unique emails when importing to avoid conflicts.

2. **Admission/Employee Numbers**: The sample files use sequential numbers (STU-2024-001 to STU-2024-010 for students, TCH-2024-001 to TCH-2024-010 for teachers). Ensure these don't conflict with existing records.

3. **Dates**: All dates are in YYYY-MM-DD format as required by the import system.

4. **Required Context**: When importing students, you'll need to provide:
   - Branch ID
   - Grade
   - Section (optional)
   - Academic Year

5. **Validation**: The import system will validate all data before committing. Review the validation results carefully.

## Testing Checklist

- [ ] Upload student CSV file
- [ ] Verify validation shows all 10 records
- [ ] Check for any validation errors
- [ ] Preview valid/invalid records
- [ ] Commit valid records
- [ ] Verify students are created in the system
- [ ] Repeat for teacher import

## Troubleshooting

If you encounter issues:

1. **File not uploading**: Check file size (max 10MB) and format (.csv, .xlsx, .xls)
2. **Validation errors**: Review the preview to see which fields have errors
3. **Duplicate errors**: Change email addresses or admission/employee numbers in the file
4. **Missing context**: Ensure all required context fields are filled during upload

## Customization

You can edit these CSV files to:
- Change email addresses
- Modify admission/employee numbers
- Update personal information
- Add or remove records

Just make sure to maintain the header row and CSV format.


