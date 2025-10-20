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
        // Get accessible branches for current user
        Route::get('/accessible', [BranchController::class, 'getAccessibleBranches']);
        
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
        Route::get('grade/{grade}/stats', [ClassController::class, 'getGradeStats']);
        Route::get('sections', [ClassController::class, 'getSections']);
        Route::get('{id}', [ClassController::class, 'show']);
        Route::put('{id}', [ClassController::class, 'update']);
        Route::delete('{id}', [ClassController::class, 'destroy']);
    });
    
    // Grade Routes - Full CRUD
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
    Route::post('attendance/bulk', [AttendanceController::class, 'markBulk']);
    Route::get('attendance/report', [AttendanceController::class, 'getReport']);
    Route::get('attendance/student/{studentId}', [AttendanceController::class, 'getStudentAttendance']);
    Route::get('attendance/teacher/{teacherId}', [AttendanceController::class, 'getTeacherAttendance']);
    Route::get('attendance/class/{grade}/{section}', [AttendanceController::class, 'getClassAttendance']);
    Route::get('attendance/report/{studentId}', [AttendanceController::class, 'generateReport']);
    Route::apiResource('attendance', AttendanceController::class);
    
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
});

