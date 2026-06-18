# School Management API - Postman Collection

This Postman collection contains all the API endpoints for the School Management System, including Login, Dashboard, Branches, Teachers, Students, Attendance, and Leaves.

## Files Included

1. **School_Management_API.postman_collection.json** - Main Postman collection file
2. **School_Management_API.postman_environment.json** - Environment variables file

## Setup Instructions

### 1. Import Collection and Environment

1. Open Postman
2. Click **Import** button (top left)
3. Select both files:
   - `School_Management_API.postman_collection.json`
   - `School_Management_API.postman_environment.json`
4. Click **Import**

### 2. Configure Environment

1. In Postman, select the environment: **School Management API - Local** (top right)
2. Update the `base_url` variable if your API is running on a different URL:
   - Default: `http://localhost:8000/api`
   - For production: Update to your production API URL

### 3. Authentication

1. Go to **Authentication > Login** request
2. Update the credentials in the request body:
   ```json
   {
       "email": "your-email@example.com",
       "password": "your-password"
   }
   ```
3. Send the request
4. The access token will be automatically saved to the environment variable `access_token`
5. All subsequent requests will use this token automatically

## Collection Structure

### 1. Authentication
- **Login** - Authenticate and get access token
- **Get Current User** - Get authenticated user information
- **Logout** - Logout and revoke token
- **Update Profile** - Update user profile
- **Change Password** - Change user password

### 2. Dashboard
- **Get Dashboard Stats** - Comprehensive dashboard statistics
- **Get Dashboard Attendance** - Attendance statistics
- **Get Top Performers** - Top performing students
- **Get Low Attendance** - Students with low attendance
- **Get Upcoming Exams** - Upcoming exam schedule

### 3. Branches
- **Get All Branches** - List all branches with pagination
- **Get Accessible Branches** - Get branches accessible to current user
- **Get Branch by ID** - Get single branch details
- **Create Branch** - Create a new branch
- **Update Branch** - Update branch details
- **Delete Branch** - Soft delete a branch
- **Get Branch Stats** - Get branch statistics
- **Export Branches** - Export branches (Excel, CSV, PDF)

### 4. Teachers
- **Get All Teachers** - List all teachers with pagination
- **Get Teacher by ID** - Get single teacher details
- **Create Teacher** - Create a new teacher
- **Update Teacher** - Update teacher details
- **Delete Teacher** - Soft delete (deactivate) a teacher
- **Restore Teacher** - Restore (reactivate) a teacher
- **Upload Teacher Profile Picture** - Upload profile picture
- **Export Teachers** - Export teachers (Excel, CSV, PDF)

### 5. Students
- **Get All Students** - List all students with pagination
- **Get Student by ID** - Get single student details
- **Create Student** - Create a new student
- **Update Student** - Update student details
- **Delete Student** - Soft delete (deactivate) a student
- **Restore Student** - Restore (reactivate) a student
- **Upload Student Profile Picture** - Upload profile picture
- **Get Student Dues** - Get fee dues for a student
- **Export Students** - Export students (Excel, CSV, PDF)

### 6. Attendance
- **Get All Attendance** - List all attendance records
- **Get Attendance by ID** - Get single attendance record
- **Mark Attendance** - Mark attendance for a student/teacher
- **Bulk Mark Attendance** - Mark attendance for multiple students
- **Update Attendance** - Update attendance record
- **Delete Attendance** - Delete attendance record
- **Get Student Attendance** - Get attendance for a specific student
- **Get Teacher Attendance** - Get attendance for a specific teacher
- **Get Class Attendance** - Get attendance for a class (grade & section)
- **Get Attendance Report** - Get attendance report with statistics
- **Export Attendance** - Export attendance (Excel, CSV, PDF)

### 7. Leaves
- **Get All Leaves** - List all leave records
- **Get Leave by ID** - Get single leave record
- **Create Leave Request** - Create a new leave request
- **Update Leave** - Update leave request (approve/reject)
- **Delete Leave** - Delete leave record
- **Get Student Leaves** - Get all leaves for a specific student
- **Get Teacher Leaves** - Get all leaves for a specific teacher

## Common Query Parameters

### Pagination
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 10)

### Filters
- `search` - Search term
- `branch_id` - Filter by branch
- `is_active` - Filter by active status (1 or 0)
- `status` - Filter by status
- `from_date` - Start date (YYYY-MM-DD)
- `to_date` - End date (YYYY-MM-DD)

### Attendance/Leave Specific
- `type` - Type: "student" or "teacher"
- `grade` - Grade level
- `section` - Section name

## Request Examples

### Login Request
```json
POST /api/login
{
    "email": "admin@example.com",
    "password": "password123"
}
```

### Create Student Request
```json
POST /api/students
{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@student.com",
    "phone": "+1234567890",
    "password": "password123",
    "branch_id": 1,
    "admission_number": "ADM001",
    "grade": "10",
    "section": "A",
    "date_of_birth": "2010-05-15",
    "gender": "Male",
    "admission_date": "2024-01-01"
}
```

### Mark Attendance Request
```json
POST /api/attendance
{
    "type": "student",
    "student_id": 1,
    "branch_id": 1,
    "grade_level": "10",
    "section": "A",
    "date": "2024-01-15",
    "status": "Present",
    "academic_year": "2024-2025",
    "remarks": "On time"
}
```

### Create Leave Request
```json
POST /api/leaves
{
    "type": "student",
    "student_id": 1,
    "branch_id": 1,
    "from_date": "2024-01-20",
    "to_date": "2024-01-22",
    "leave_type": "Sick Leave",
    "reason": "Fever and cold",
    "remarks": "Doctor advised rest"
}
```

## Response Format

All API responses follow this format:

### Success Response
```json
{
    "success": true,
    "message": "Operation successful",
    "data": { ... }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error message",
    "errors": { ... }
}
```

## Authentication

All protected endpoints require authentication using Bearer token:

```
Authorization: Bearer {access_token}
```

The access token is automatically set after successful login and is stored in the environment variable.

## Notes

1. **Token Expiration**: Access tokens expire after 30 days. You'll need to login again after expiration.

2. **Rate Limiting**: The API has rate limiting:
   - Protected routes: 180 requests per minute
   - Import routes: 30 requests per minute
   - Upload routes: 60 requests per minute

3. **Soft Delete**: Delete operations for Teachers and Students are soft deletes (deactivation). Use the Restore endpoint to reactivate.

4. **File Uploads**: For profile picture uploads, use form-data with the file field named `profile_picture`.

5. **Export Formats**: Export endpoints support:
   - `excel` - Excel format (.xlsx)
   - `csv` - CSV format
   - `pdf` - PDF format

## Troubleshooting

### Token Not Working
- Make sure you've logged in and the token is saved
- Check if the token has expired (30 days)
- Try logging in again

### 401 Unauthorized
- Verify the access token is set in the environment
- Check if the token is included in the Authorization header
- Try logging in again

### 422 Validation Error
- Check the request body format
- Verify all required fields are provided
- Check field types and formats (dates, emails, etc.)

### 404 Not Found
- Verify the endpoint URL is correct
- Check if the resource ID exists
- Ensure you have access to the resource

## Support

For API documentation and support, refer to the main project documentation or contact the development team.



