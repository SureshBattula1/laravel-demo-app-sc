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
use App\Http\Controllers\FeeTypeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\LeaveController;
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
use App\Http\Controllers\GradeController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionManagementController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\SectionSubjectController;
use App\Http\Controllers\ExamTermController;
use App\Http\Controllers\ExamScheduleController;
use App\Http\Controllers\GlobalUploadController;
use App\Http\Controllers\ExamMarkController;
use App\Http\Controllers\UserPreferenceController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\CommunicationController;

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

// Protected Routes with rate limiting (180 requests per minute = 3 per second)
Route::middleware(['auth:sanctum', 'throttle:180,1'])->group(function () {
    
    // Auth Routes
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/change-password', [AuthController::class, 'changePassword']);
    
    // User Preferences Routes
    Route::prefix('preferences')->group(function () {
        Route::get('/', [UserPreferenceController::class, 'index']);
        Route::put('/', [UserPreferenceController::class, 'update']);
        Route::put('/{key}', [UserPreferenceController::class, 'updateSingle']);
        Route::post('/reset', [UserPreferenceController::class, 'reset']);
    });
    
    // Branch Routes - Enhanced Multi-Branch Management
    Route::prefix('branches')->group(function () {
        // Get accessible branches for current user
        Route::get('/accessible', [BranchController::class, 'getAccessibleBranches']);
        
        // List and create
        Route::get('/', [BranchController::class, 'index']);
        Route::post('/', [BranchController::class, 'store']);
        
        // Export
        Route::get('export', [BranchController::class, 'export']);
        
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
    Route::get('teachers/export', [TeacherController::class, 'export']);
    Route::get('teachers/{id}', [TeacherController::class, 'show']);
    Route::put('teachers/{id}', [TeacherController::class, 'update']);
    Route::delete('teachers/{id}', [TeacherController::class, 'destroy']); // Soft delete (deactivate)
    Route::post('teachers/{id}/restore', [TeacherController::class, 'restore']); // Restore (reactivate)
    Route::post('teachers/{id}/upload-profile-picture', [TeacherController::class, 'uploadProfilePicture']);
    
    // Student Routes
    Route::get('students', [StudentController::class, 'index']);
    Route::post('students', [StudentController::class, 'store']);
    Route::get('students/export', [StudentController::class, 'export']);
    Route::get('students/by-user/{userId}', [StudentController::class, 'getByUserId']);
    Route::get('students/{id}', [StudentController::class, 'show']);
    Route::put('students/{id}', [StudentController::class, 'update']);
    Route::delete('students/{id}', [StudentController::class, 'destroy']); // Soft delete (deactivate)
    Route::post('students/{id}/restore', [StudentController::class, 'restore']); // Restore (reactivate)
    Route::post('students/promote', [StudentController::class, 'promote']);
    Route::post('students/{id}/upload-profile-picture', [StudentController::class, 'uploadProfilePicture']);
    
    // Class & Section Routes - Full CRUD
    Route::prefix('classes')->group(function () {
        Route::get('/', [ClassController::class, 'index']);
        Route::post('/', [ClassController::class, 'store']);
        Route::get('grades', [ClassController::class, 'getGrades']);
        Route::get('grade/{grade}/stats', [ClassController::class, 'getGradeStats']);
        Route::get('sections', [ClassController::class, 'getSections']);
        Route::get('{id}', [ClassController::class, 'show']);
        Route::put('{id}', [ClassController::class, 'update']);
        Route::delete('{id}', [ClassController::class, 'destroy']);
    });
    
    // Grade Routes - Full CRUD
    Route::get('grades/export', [GradeController::class, 'export']);
    Route::apiResource('grades', GradeController::class)->parameters([
        'grades' => 'value'  // Use grade value instead of id
    ]);
    
    // Student Group Routes
    Route::apiResource('student-groups', StudentGroupController::class);
    Route::post('student-groups/{id}/add-member', [StudentGroupController::class, 'addMember']);
    Route::delete('student-groups/{id}/members/{studentId}', [StudentGroupController::class, 'removeMember']);
    
    // Section Routes - Full CRUD
    Route::prefix('sections')->group(function () {
        Route::get('/', [SectionController::class, 'index']);
        Route::post('/', [SectionController::class, 'store']);
        Route::get('export', [SectionController::class, 'export']);
        Route::get('{id}/subjects', [SectionSubjectController::class, 'getSectionSubjects']);
        Route::get('{id}', [SectionController::class, 'show']);
        Route::put('{id}', [SectionController::class, 'update']);
        Route::delete('{id}', [SectionController::class, 'destroy']);
        Route::put('{id}/toggle-status', [SectionController::class, 'toggleStatus']);
    });
    
    // Section-Subject Assignment Routes
    Route::prefix('section-subjects')->group(function () {
        Route::get('/', [SectionSubjectController::class, 'index']);
        Route::post('/', [SectionSubjectController::class, 'assignSubject']);
        Route::post('bulk', [SectionSubjectController::class, 'assignMultipleSubjects']);
        Route::post('copy', [SectionSubjectController::class, 'copySubjects']);
        Route::put('{id}', [SectionSubjectController::class, 'updateAssignment']);
        Route::delete('{id}', [SectionSubjectController::class, 'removeSubject']);
    });
    
    // Exam Routes
    Route::apiResource('exams', ExamController::class);
    Route::get('exams/{id}/statistics', [ExamController::class, 'statistics']);
    Route::post('exam-results', [ExamController::class, 'storeResult']);
    Route::get('exams/{id}/results', [ExamController::class, 'getResults']);
    Route::get('students/{studentId}/results', [ExamController::class, 'getStudentResults']);
    
    // Exam Terms
    Route::prefix('exam-terms')->group(function () {
        Route::get('/', [ExamTermController::class, 'index']);
        Route::post('/', [ExamTermController::class, 'store']);
        Route::get('{id}', [ExamTermController::class, 'show']);
        Route::put('{id}', [ExamTermController::class, 'update']);
        Route::delete('{id}', [ExamTermController::class, 'destroy']);
    });
    
    // Exam Schedules
    Route::prefix('exam-schedules')->group(function () {
        Route::get('/', [ExamScheduleController::class, 'index']);
        Route::post('/', [ExamScheduleController::class, 'store']);
        Route::get('{id}/students', [ExamScheduleController::class, 'getStudents']);
        Route::get('{id}', [ExamScheduleController::class, 'show'])->where('id', '[0-9]+');
        Route::put('{id}', [ExamScheduleController::class, 'update']);
        Route::delete('{id}', [ExamScheduleController::class, 'destroy']);
    });

    // Exam Marks
    Route::prefix('exam-marks')->group(function () {
        Route::get('schedule/{scheduleId}', [ExamMarkController::class, 'getMarks']);
        Route::post('schedule/{scheduleId}', [ExamMarkController::class, 'storeMarks']);
        Route::get('student/{studentId}', [ExamMarkController::class, 'getStudentMarks']);
    });
    
    // Fee Routes
    // Fee Types Routes
    Route::get('fee-types', [FeeTypeController::class, 'index']);
    Route::post('fee-types', [FeeTypeController::class, 'store']);
    Route::get('fee-types/{id}', [FeeTypeController::class, 'show']);
    Route::put('fee-types/{id}', [FeeTypeController::class, 'update']);
    Route::delete('fee-types/{id}', [FeeTypeController::class, 'destroy']);
    Route::put('fee-types/{id}/toggle-status', [FeeTypeController::class, 'toggleStatus']);
    
    Route::get('fee-structures', [FeeController::class, 'indexStructures']);
    Route::post('fee-structures', [FeeController::class, 'storeStructure']);
    Route::get('fee-structures/{id}', [FeeController::class, 'show']);
    Route::put('fee-structures/{id}', [FeeController::class, 'updateStructure']);
    Route::delete('fee-structures/{id}', [FeeController::class, 'destroyStructure']);
    
    Route::get('fee-payments', [FeeController::class, 'indexPayments']);
    Route::post('fee-payments', [FeeController::class, 'recordPayment']);
    Route::get('fee-payments/{id}', [FeeController::class, 'showPayment']);
    Route::get('students/{studentId}/fees', [FeeController::class, 'getStudentFees']);
    
    // Attendance Routes (specific routes MUST come before apiResource)
    Route::get('attendance/export', [AttendanceController::class, 'export']);
    Route::post('attendance/bulk', [AttendanceController::class, 'markBulk']);
    Route::get('attendance/report', [AttendanceController::class, 'getReport']);
    Route::get('attendance/student/{studentId}', [AttendanceController::class, 'getStudentAttendance']);
    Route::get('attendance/teacher/{teacherId}', [AttendanceController::class, 'getTeacherAttendance']);
    Route::get('attendance/class/{grade}/{section}', [AttendanceController::class, 'getClassAttendance']);
    Route::get('attendance/report/{studentId}', [AttendanceController::class, 'generateReport']);
    Route::apiResource('attendance', AttendanceController::class);
    
    // Leave Routes (specific routes MUST come before apiResource)
    Route::get('leaves/student/{studentId}', [LeaveController::class, 'getStudentLeaves']);
    Route::get('leaves/teacher/{teacherId}', [LeaveController::class, 'getTeacherLeaves']);
    Route::apiResource('leaves', LeaveController::class);
    
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
    
    // OLD Holiday Routes - DISABLED (using HolidayController below instead)
    // Route::apiResource('holidays', EventController::class);
    // Route::get('holidays/year/{year}', [EventController::class, 'getHolidaysByYear']);
    
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
    
    // Accounts Module Routes - Income & Expense Tracking
    Route::prefix('accounts')->group(function () {
        Route::get('dashboard', [AccountController::class, 'getDashboard']);
        Route::get('categories', [AccountController::class, 'getCategories']);
    });
    
    // Transaction Routes - Full CRUD
    Route::get('transactions/export', [TransactionController::class, 'export']);
    Route::apiResource('transactions', TransactionController::class);
    Route::post('transactions/{id}/approve', [TransactionController::class, 'approve']);
    Route::post('transactions/{id}/reject', [TransactionController::class, 'reject']);
    
    // Invoice Routes - Advanced with transaction integration
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::post('/', [InvoiceController::class, 'store']); // Manual invoice
        Route::get('stats', [InvoiceController::class, 'getStats']);
        Route::get('search-transactions', [InvoiceController::class, 'searchTransactions']); // Search transactions
        Route::post('generate-from-transactions', [InvoiceController::class, 'generateFromTransactions']); // Generate from transactions
        Route::get('{id}', [InvoiceController::class, 'show']);
        Route::put('{id}', [InvoiceController::class, 'update']);
        Route::delete('{id}', [InvoiceController::class, 'destroy']);
        Route::post('{id}/payment', [InvoiceController::class, 'recordPayment']);
        Route::post('{id}/send', [InvoiceController::class, 'sendInvoice']);
    });
    
    // Holiday Routes - with role-based access
    Route::prefix('holidays')->group(function () {
        Route::get('/', [HolidayController::class, 'index']);
        Route::post('/', [HolidayController::class, 'store']);
        Route::get('export', [HolidayController::class, 'export']);
        Route::get('upcoming', [HolidayController::class, 'getUpcoming']);
        Route::get('calendar/{year}/{month}', [HolidayController::class, 'getCalendarData']);
        Route::get('{id}', [HolidayController::class, 'show']);
        Route::put('{id}', [HolidayController::class, 'update']);
        Route::delete('{id}', [HolidayController::class, 'destroy']);
    });
    
    // Settings Module Routes - User, Role & Permission Management
    // Users Management
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::get('/all', [UserController::class, 'all']);
        Route::get('/{id}', [UserController::class, 'show']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
        Route::patch('/{id}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::post('/{id}/reset-password', [UserController::class, 'resetPassword']);
        
        // Permission Management for Users
        Route::get('/{id}/permissions', [UserController::class, 'getPermissions']);
        Route::post('/{id}/permissions', [UserController::class, 'updatePermissions']);
        Route::post('/{id}/roles', [UserController::class, 'assignRoles']);
    });
    
    // Roles Management
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/all', [RoleController::class, 'all']);
        Route::get('/{id}', [RoleController::class, 'show']);
        Route::post('/', [RoleController::class, 'store']);
        Route::put('/{id}', [RoleController::class, 'update']);
        Route::delete('/{id}', [RoleController::class, 'destroy']);
        Route::post('/{id}/permissions', [RoleController::class, 'assignPermissions']);
        Route::get('/{id}/permissions', [RoleController::class, 'permissions']);
    });
    
    // Permissions Management - Combined routes
    Route::prefix('permissions')->group(function () {
        // Settings Module - CRUD operations
        Route::get('/', [PermissionManagementController::class, 'index']);
        Route::get('/all', [PermissionManagementController::class, 'all']);
        Route::get('/by-module', [PermissionManagementController::class, 'byModule']);
        Route::post('/', [PermissionManagementController::class, 'store']);
        Route::put('/{id}', [PermissionManagementController::class, 'update']);
        Route::delete('/{id}', [PermissionManagementController::class, 'destroy']);
        
        // Legacy permission controller endpoints
        Route::get('/roles', [App\Http\Controllers\PermissionController::class, 'getRoles']);
        Route::get('/modules', [App\Http\Controllers\PermissionController::class, 'getModules']);
        Route::get('/list', [App\Http\Controllers\PermissionController::class, 'getPermissions']);
        Route::get('/user/{id}/permissions', [App\Http\Controllers\PermissionController::class, 'getUserPermissions']);
        
        // Details endpoint for settings
        Route::get('/{id}', [PermissionManagementController::class, 'show']);
        
        // Admin-only permission management
        Route::middleware('role:SuperAdmin,BranchAdmin')->group(function () {
            Route::post('/role/{roleId}/sync', [App\Http\Controllers\PermissionController::class, 'syncRolePermissions']);
            Route::post('/user/{userId}/grant', [App\Http\Controllers\PermissionController::class, 'grantUserPermission']);
            Route::post('/user/{userId}/revoke', [App\Http\Controllers\PermissionController::class, 'revokeUserPermission']);
            Route::post('/roles', [App\Http\Controllers\PermissionController::class, 'createRole']);
            Route::post('/modules', [App\Http\Controllers\PermissionController::class, 'createModule']);
        });
    });
    
    // Modules Management
    Route::prefix('modules')->group(function () {
        Route::get('/', [ModuleController::class, 'index']);
        Route::get('/{id}', [ModuleController::class, 'show']);
        Route::post('/', [ModuleController::class, 'store']);
        Route::put('/{id}', [ModuleController::class, 'update']);
        Route::delete('/{id}', [ModuleController::class, 'destroy']);
    });
    
    // Import Module - Data Import System (Rate limited - 30 per minute)
    Route::prefix('imports')->middleware('throttle:30,1')->group(function () {
        Route::get('/modules', [App\Http\Controllers\ImportController::class, 'getModules']);
        Route::get('/history', [App\Http\Controllers\ImportController::class, 'history']);
        Route::get('/template/{entity}', [App\Http\Controllers\ImportController::class, 'downloadTemplate']);
        
        // Entity-specific import routes
        Route::post('/{entity}/upload', [App\Http\Controllers\ImportController::class, 'upload']);
        Route::post('/{entity}/validate/{batchId}', [App\Http\Controllers\ImportController::class, 'validate']);
        Route::get('/{entity}/preview/{batchId}', [App\Http\Controllers\ImportController::class, 'preview']);
        Route::post('/{entity}/commit/{batchId}', [App\Http\Controllers\ImportController::class, 'commit']);
        Route::delete('/{entity}/cancel/{batchId}', [App\Http\Controllers\ImportController::class, 'cancel']);
    });

    // Global File Upload Routes (Rate limited - 60 per minute)
    Route::prefix('uploads')->middleware('throttle:60,1')->group(function () {
        Route::post('/', [GlobalUploadController::class, 'upload']);
        Route::post('/multiple', [GlobalUploadController::class, 'uploadMultiple']);
        Route::delete('/', [GlobalUploadController::class, 'delete']);
        Route::get('/file-info', [GlobalUploadController::class, 'getFileInfo']);
        Route::get('/exists', [GlobalUploadController::class, 'checkFileExists']);
    });

    // Attachments Routes (Universal Attachments)
    Route::prefix('attachments')->group(function () {
        Route::post('/save', [GlobalUploadController::class, 'saveAttachment']);
        Route::get('/{module}/{moduleId}', [GlobalUploadController::class, 'getAttachments']);
        Route::get('/{module}/{moduleId}/{attachmentId}/download', [GlobalUploadController::class, 'downloadAttachment']);
        Route::delete('/{module}/{moduleId}/{attachmentId}', [GlobalUploadController::class, 'deleteAttachment']);
    });
    
    // Admission Management Routes
    Route::prefix('admissions')->group(function () {
        Route::get('/', [AdmissionController::class, 'index']);
        Route::post('/', [AdmissionController::class, 'store']);
        Route::get('/export', [AdmissionController::class, 'export']);
        Route::get('/{id}', [AdmissionController::class, 'show']);
        Route::put('/{id}', [AdmissionController::class, 'update']);
        Route::delete('/{id}', [AdmissionController::class, 'destroy']);
        Route::post('/{id}/update-status', [AdmissionController::class, 'updateStatus']);
        Route::post('/{id}/convert-to-student', [AdmissionController::class, 'convertToStudent']);
    });
    
    // Communication System Routes
    Route::prefix('communications')->group(function () {
        // Notifications
        Route::get('/notifications', [CommunicationController::class, 'getNotifications']);
        Route::post('/notifications', [CommunicationController::class, 'createNotification']);
        Route::post('/notifications/{id}/read', [CommunicationController::class, 'markAsRead']);
        
        // Announcements
        Route::get('/announcements', [CommunicationController::class, 'getAnnouncements']);
        Route::post('/announcements', [CommunicationController::class, 'createAnnouncement']);
        Route::get('/announcements/{id}', [CommunicationController::class, 'getAnnouncement']);
        Route::put('/announcements/{id}', [CommunicationController::class, 'updateAnnouncement']);
        Route::delete('/announcements/{id}', [CommunicationController::class, 'deleteAnnouncement']);
        
        // Circulars
        Route::get('/circulars', [CommunicationController::class, 'getCirculars']);
        Route::post('/circulars', [CommunicationController::class, 'createCircular']);
        Route::get('/circulars/{id}', [CommunicationController::class, 'getCircular']);
        Route::put('/circulars/{id}', [CommunicationController::class, 'updateCircular']);
        Route::delete('/circulars/{id}', [CommunicationController::class, 'deleteCircular']);
        Route::post('/circulars/{id}/acknowledge', [CommunicationController::class, 'acknowledgeCircular']);
    });
});

