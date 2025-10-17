<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\EnhancedBranchController;
use App\Http\Controllers\BranchTransferController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\FeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\TransportController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ClassSectionController;
use App\Http\Controllers\StudentGroupController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\SectionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Health Check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'service' => 'MySchool API'
    ]);
});

// Protected Routes with rate limiting
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    
    // Auth Routes
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);
    
    // Branch Routes - Enhanced Multi-Branch Management
    Route::prefix('branches')->group(function () {
        // List and create
        Route::get('/', [BranchController::class, 'index']);
        Route::post('/', [BranchController::class, 'store']);
        
        // Deleted branches (soft deleted - status = Closed)
        Route::get('deleted', [BranchController::class, 'getDeleted']);
        
        // Bulk operations
        Route::post('bulk-delete', [BranchController::class, 'bulkDelete']);
        Route::post('bulk-restore', [BranchController::class, 'bulkRestore']);
        
        // Hierarchy and analytics
        Route::get('hierarchy', [BranchController::class, 'getHierarchy']);
        Route::get('hierarchy/{id}', [BranchController::class, 'getHierarchy']);
        Route::get('locations', [BranchController::class, 'getBranchLocations']);
        Route::get('comparative-analytics', [BranchController::class, 'getComparativeAnalytics']);
        
        // Single branch operations
        Route::get('{id}', [BranchController::class, 'show']);
        Route::put('{id}', [BranchController::class, 'update']);
        Route::delete('{id}', [BranchController::class, 'destroy']);
        Route::post('{id}/restore', [BranchController::class, 'restore']);
        Route::get('{id}/stats', [BranchController::class, 'stats']);
        Route::put('{id}/toggle-status', [BranchController::class, 'toggleStatus']);
        Route::put('{id}/capacity', [BranchController::class, 'updateCapacity']);
        Route::get('{id}/settings', [BranchController::class, 'getSettings']);
        Route::post('{id}/settings', [BranchController::class, 'updateSettings']);
    });

    // Branch Transfer Routes
    Route::prefix('branch-transfers')->group(function () {
        Route::get('/', [BranchTransferController::class, 'index']);
        Route::post('/', [BranchTransferController::class, 'store']);
        Route::get('statistics', [BranchTransferController::class, 'getStatistics']);
        Route::get('{id}', [BranchTransferController::class, 'show']);
        Route::post('{id}/approve', [BranchTransferController::class, 'approve']);
        Route::post('{id}/reject', [BranchTransferController::class, 'reject']);
        Route::post('{id}/complete', [BranchTransferController::class, 'complete']);
        Route::post('{id}/cancel', [BranchTransferController::class, 'cancel']);
    });
    
    // Department Routes
    Route::apiResource('departments', DepartmentController::class);
    Route::put('departments/{id}/toggle-status', [DepartmentController::class, 'toggleStatus']);
    
    // Subject Routes
    Route::apiResource('subjects', SubjectController::class);
    Route::get('subjects/by-grade/{grade}', [SubjectController::class, 'byGrade']);
    Route::get('subjects/by-department/{departmentId}', [SubjectController::class, 'byDepartment']);
    
    // Teacher Routes
    Route::get('teachers', [TeacherController::class, 'index']);
    Route::post('teachers', [TeacherController::class, 'store']);
    Route::get('teachers/{id}', [TeacherController::class, 'show']);
    Route::put('teachers/{id}', [TeacherController::class, 'update']);
    Route::delete('teachers/{id}', [TeacherController::class, 'destroy']);
    
    // Student Routes
    Route::get('students', [StudentController::class, 'index']);
    Route::post('students', [StudentController::class, 'store']);
    Route::get('students/{id}', [StudentController::class, 'show']);
    Route::put('students/{id}', [StudentController::class, 'update']);
    Route::delete('students/{id}', [StudentController::class, 'destroy']);
    Route::post('students/promote', [StudentController::class, 'promote']);
    
    // Class & Section Routes - Full CRUD
    Route::prefix('classes')->group(function () {
        Route::get('/', [ClassController::class, 'index']);
        Route::post('/', [ClassController::class, 'store']);
        Route::get('grades', [ClassController::class, 'getGrades']);
        Route::get('sections', [ClassController::class, 'getSections']);
        Route::get('{id}', [ClassController::class, 'show']);
        Route::put('{id}', [ClassController::class, 'update']);
        Route::delete('{id}', [ClassController::class, 'destroy']);
    });
    
    // Student Group Routes
    Route::apiResource('student-groups', StudentGroupController::class);
    Route::post('student-groups/{id}/add-member', [StudentGroupController::class, 'addMember']);
    Route::delete('student-groups/{id}/members/{studentId}', [StudentGroupController::class, 'removeMember']);
    
    // Section Routes - Full CRUD
    Route::prefix('sections')->group(function () {
        Route::get('/', [SectionController::class, 'index']);
        Route::post('/', [SectionController::class, 'store']);
        Route::get('{id}', [SectionController::class, 'show']);
        Route::put('{id}', [SectionController::class, 'update']);
        Route::delete('{id}', [SectionController::class, 'destroy']);
        Route::put('{id}/toggle-status', [SectionController::class, 'toggleStatus']);
    });
    
    // Exam Routes
    Route::apiResource('exams', ExamController::class);
    Route::get('exams/{id}/statistics', [ExamController::class, 'statistics']);
    Route::post('exam-results', [ExamController::class, 'storeResult']);
    Route::get('exams/{id}/results', [ExamController::class, 'getResults']);
    Route::get('students/{studentId}/results', [ExamController::class, 'getStudentResults']);
    
    // Fee Routes
    Route::get('fee-structures', [FeeController::class, 'indexStructures']);
    Route::post('fee-structures', [FeeController::class, 'storeStructure']);
    Route::put('fee-structures/{id}', [FeeController::class, 'updateStructure']);
    Route::delete('fee-structures/{id}', [FeeController::class, 'destroyStructure']);
    
    Route::get('fee-payments', [FeeController::class, 'indexPayments']);
    Route::post('fee-payments', [FeeController::class, 'recordPayment']);
    Route::get('students/{studentId}/fees', [FeeController::class, 'getStudentFees']);
    
    // Attendance Routes
    Route::apiResource('attendance', AttendanceController::class);
    Route::post('attendance/bulk', [AttendanceController::class, 'markBulk']);
    Route::get('attendance/report', [AttendanceController::class, 'getReport']);
    Route::get('attendance/student/{studentId}', [AttendanceController::class, 'getStudentAttendance']);
    Route::get('attendance/class/{grade}/{section}', [AttendanceController::class, 'getClassAttendance']);
    Route::get('attendance/report/{studentId}', [AttendanceController::class, 'generateReport']);
    
    // Library Routes
    Route::apiResource('books', LibraryController::class);
    Route::post('books/{id}/issue', [LibraryController::class, 'issueBook']);
    Route::post('book-issues/{id}/return', [LibraryController::class, 'returnBook']);
    Route::get('book-issues/active', [LibraryController::class, 'getActiveIssues']);
    Route::get('book-issues/overdue', [LibraryController::class, 'getOverdueIssues']);
    Route::get('students/{studentId}/book-issues', [LibraryController::class, 'getStudentIssues']);
    
    // Transport Routes
    Route::apiResource('transport-routes', TransportController::class);
    Route::apiResource('vehicles', TransportController::class);
    Route::get('transport-routes/{id}/students', [TransportController::class, 'getRouteStudents']);
    
    // Event Routes
    Route::apiResource('events', EventController::class);
    Route::get('events/upcoming', [EventController::class, 'getUpcoming']);
    Route::get('events/by-type/{type}', [EventController::class, 'getByType']);
    
    // Holiday Routes
    Route::apiResource('holidays', EventController::class);
    Route::get('holidays/year/{year}', [EventController::class, 'getHolidaysByYear']);
    
    // Timetable Routes
    Route::apiResource('timetables', TimetableController::class);
    Route::get('timetables/class/{grade}/{section}', [TimetableController::class, 'getByClass']);
    
    // Dashboard Routes (Secure - Role-Based Access)
    Route::get('dashboard', [DashboardController::class, 'getStats']); // Main dashboard endpoint
    Route::prefix('dashboard')->group(function () {
        Route::get('stats', [DashboardController::class, 'getStats']);
        Route::get('attendance', [DashboardController::class, 'getAttendance']);
        Route::get('top-performers', [DashboardController::class, 'getTopPerformers']);
        Route::get('low-attendance', [DashboardController::class, 'getLowAttendance']);
        Route::get('upcoming-exams', [DashboardController::class, 'getUpcomingExams']);
        Route::get('student-results', [DashboardController::class, 'getStudentResults']);
        Route::get('children-performance/{parentId}', [DashboardController::class, 'getChildrenPerformance']);
    });
});

