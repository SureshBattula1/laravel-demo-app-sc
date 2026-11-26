<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchSmsGatewayConfigController;
use App\Http\Controllers\BranchTransferController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DriverPortalController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamMarkController;
use App\Http\Controllers\ExamScheduleController;
use App\Http\Controllers\ExamTermController;
use App\Http\Controllers\FeeController;
use App\Http\Controllers\FeeDuesController;
use App\Http\Controllers\FeeReportController;
use App\Http\Controllers\FeeTypeController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\GlobalUploadController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\IdCardController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\Library\LibraryCatalogController;
use App\Http\Controllers\Library\LibraryCirculationController;
use App\Http\Controllers\Library\LibraryDashboardController;
use App\Http\Controllers\Library\LibraryFineController;
use App\Http\Controllers\Library\LibraryInventoryController;
use App\Http\Controllers\Library\LibraryMemberController;
use App\Http\Controllers\Library\LibraryProcurementController;
use App\Http\Controllers\Library\LibraryReportController;
use App\Http\Controllers\Library\LibraryReservationController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NotificationCampaignController;
use App\Http\Controllers\PermissionManagementController;
use App\Http\Controllers\RealTimeNotificationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\SectionSubjectController;
use App\Http\Controllers\SmsBulkQueueLogController;
use App\Http\Controllers\SmsBulkSendController;
use App\Http\Controllers\SmsTemplateController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentGroupController;
use App\Http\Controllers\StudentTransportController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransportAssignmentController;
use App\Http\Controllers\TransportBoardingController;
use App\Http\Controllers\TransportDashboardController;
use App\Http\Controllers\TransportDriverController;
use App\Http\Controllers\TransportFinanceController;
use App\Http\Controllers\TransportRouteController;
use App\Http\Controllers\TransportStopController;
use App\Http\Controllers\TransportTrackingController;
use App\Http\Controllers\TransportTripController;
use App\Http\Controllers\TwilioWhatsAppStatusWebhookController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPreferenceController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\WhatsAppBulkQueueLogController;
use App\Http\Controllers\WhatsAppBulkSendController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public auth routes. Registration stays authenticated so anonymous callers cannot mint tokens.
Route::post('/register', [AuthController::class, 'register'])
    ->middleware(['auth:sanctum', 'permission:users.create', 'throttle:10,1']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
Route::post('/webhooks/twilio/whatsapp-status', TwilioWhatsAppStatusWebhookController::class);

// Health Check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'service' => 'MySchool API',
    ]);
});

// Protected Routes with rate limiting (180 requests per minute = 3 per second)
Route::middleware(['auth:sanctum', 'school.account.active', 'throttle:180,1'])->group(function () {

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
        Route::post('/', [BranchController::class, 'store'])->middleware('permission:branches.create');

        // Export (requires branches.export permission)
        Route::get('export', [BranchController::class, 'export'])->middleware('permission:branches.export');

        // Deleted branches (soft deleted - status = Closed)
        Route::get('deleted', [BranchController::class, 'getDeleted'])->middleware('permission:branches.view');

        // Bulk operations
        Route::post('bulk-delete', [BranchController::class, 'bulkDelete'])->middleware('permission:branches.delete');
        Route::post('bulk-restore', [BranchController::class, 'bulkRestore'])->middleware('permission:branches.edit');

        // Hierarchy and analytics
        Route::get('hierarchy', [BranchController::class, 'getHierarchy']);
        Route::get('hierarchy/{id}', [BranchController::class, 'getHierarchy']);
        Route::get('locations', [BranchController::class, 'getBranchLocations']);
        Route::get('comparative-analytics', [BranchController::class, 'getComparativeAnalytics'])->middleware('permission:branches.stats');

        // Branch SMS gateway configs (bulk messaging; credentials encrypted at rest)
        Route::get('{id}/sms-gateway-config', [BranchSmsGatewayConfigController::class, 'index'])
            ->middleware('permission:bulk_management.view');
        Route::put('{id}/sms-gateway-config', [BranchSmsGatewayConfigController::class, 'update'])
            ->middleware('permission:bulk_management.edit');
        Route::post('{id}/sms-gateway-config/send-test', [BranchSmsGatewayConfigController::class, 'sendTest'])
            ->middleware(['permission:bulk_management.edit', 'throttle:5,1']);

        Route::get('sms-bulk-logs', [SmsBulkQueueLogController::class, 'indexAll'])
            ->middleware('permission:bulk_management.view');
        Route::get('whatsapp-bulk-logs', [WhatsAppBulkQueueLogController::class, 'indexAll'])
            ->middleware('permission:bulk_management.view');

        // SMS templates & bulk send (personalized #tags#)
        Route::get('sms-templates', [SmsTemplateController::class, 'indexAll'])
            ->middleware('permission:bulk_management.view');
        Route::get('sms-templates/tag-catalog', [SmsTemplateController::class, 'tagCatalog'])
            ->middleware('permission:bulk_management.view');
        Route::get('{id}/sms-templates', [SmsTemplateController::class, 'index'])
            ->middleware('permission:bulk_management.view');
        Route::post('{id}/sms-templates', [SmsTemplateController::class, 'store'])
            ->middleware('permission:bulk_management.create');
        Route::put('{id}/sms-templates/{templateId}', [SmsTemplateController::class, 'update'])
            ->middleware('permission:bulk_management.edit');
        Route::delete('{id}/sms-templates/{templateId}', [SmsTemplateController::class, 'destroy'])
            ->middleware('permission:bulk_management.delete');
        Route::post('{id}/sms-templates/preview', [SmsTemplateController::class, 'preview'])
            ->middleware('permission:bulk_management.view');
        Route::get('{id}/sms-recipient-options', [SmsBulkSendController::class, 'recipientOptions'])
            ->middleware('permission:bulk_management.view');
        Route::get('{id}/sms-student-search', [SmsBulkSendController::class, 'searchStudents'])
            ->middleware('permission:bulk_management.view');
        Route::post('{id}/sms-bulk-send/preview', [SmsBulkSendController::class, 'preview'])
            ->middleware('permission:bulk_management.view');
        Route::post('{id}/sms-bulk-send', [SmsBulkSendController::class, 'store'])
            ->middleware('permission:bulk_management.create');
        Route::get('{id}/sms-bulk-logs', [SmsBulkQueueLogController::class, 'index'])
            ->middleware('permission:bulk_management.view');
        Route::get('{id}/sms-bulk-logs/{queueId}', [SmsBulkQueueLogController::class, 'show'])
            ->middleware('permission:bulk_management.view');
        Route::post('{id}/sms-bulk-logs/{queueId}/resend', [SmsBulkQueueLogController::class, 'resend'])
            ->middleware('permission:bulk_management.create');

        // WhatsApp bulk (same templates & audience rules as SMS; Twilio WhatsApp only)
        Route::get('{id}/whatsapp-recipient-options', [WhatsAppBulkSendController::class, 'recipientOptions'])
            ->middleware('permission:bulk_management.view');
        Route::get('{id}/whatsapp-student-search', [WhatsAppBulkSendController::class, 'searchStudents'])
            ->middleware('permission:bulk_management.view');
        Route::post('{id}/whatsapp-bulk-send/preview', [WhatsAppBulkSendController::class, 'preview'])
            ->middleware('permission:bulk_management.view');
        Route::post('{id}/whatsapp-bulk-send', [WhatsAppBulkSendController::class, 'store'])
            ->middleware('permission:bulk_management.create');
        Route::get('{id}/whatsapp-bulk-logs', [WhatsAppBulkQueueLogController::class, 'index'])
            ->middleware('permission:bulk_management.view');
        Route::get('{id}/whatsapp-bulk-logs/{queueId}', [WhatsAppBulkQueueLogController::class, 'show'])
            ->middleware('permission:bulk_management.view');
        Route::post('{id}/whatsapp-bulk-logs/{queueId}/resend', [WhatsAppBulkQueueLogController::class, 'resend'])
            ->middleware('permission:bulk_management.create');

        // Single branch operations
        Route::get('{id}', [BranchController::class, 'show']);
        Route::put('{id}', [BranchController::class, 'update'])->middleware('permission:branches.edit');
        Route::delete('{id}', [BranchController::class, 'destroy'])->middleware('permission:branches.delete');
        Route::post('{id}/restore', [BranchController::class, 'restore'])->middleware('permission:branches.edit');
        Route::get('{id}/stats', [BranchController::class, 'stats']);
        Route::put('{id}/toggle-status', [BranchController::class, 'toggleStatus'])->middleware('permission:branches.edit');
        Route::put('{id}/capacity', [BranchController::class, 'updateCapacity'])->middleware('permission:branches.edit');
        Route::get('{id}/settings', [BranchController::class, 'getSettings']);
        Route::post('{id}/settings', [BranchController::class, 'updateSettings'])->middleware('permission:branches.edit');
    });

    // Branch Transfer Routes
    Route::prefix('branch-transfers')->group(function () {
        Route::get('/', [BranchTransferController::class, 'index']);
        Route::post('/', [BranchTransferController::class, 'store'])->middleware('permission:branches.edit');
        Route::get('statistics', [BranchTransferController::class, 'getStatistics']);
        Route::get('{id}', [BranchTransferController::class, 'show']);
        Route::post('{id}/approve', [BranchTransferController::class, 'approve'])->middleware('permission:branches.edit');
        Route::post('{id}/reject', [BranchTransferController::class, 'reject'])->middleware('permission:branches.edit');
        Route::post('{id}/complete', [BranchTransferController::class, 'complete'])->middleware('permission:branches.edit');
        Route::post('{id}/cancel', [BranchTransferController::class, 'cancel'])->middleware('permission:branches.edit');
    });

    // Department Routes
    Route::apiResource('departments', DepartmentController::class)->only(['index', 'show']);
    Route::apiResource('departments', DepartmentController::class)->only(['store'])->middleware('permission:departments.create');
    Route::apiResource('departments', DepartmentController::class)->only(['update'])->middleware('permission:departments.edit');
    Route::apiResource('departments', DepartmentController::class)->only(['destroy'])->middleware('permission:departments.delete');
    Route::put('departments/{id}/toggle-status', [DepartmentController::class, 'toggleStatus'])->middleware('permission:departments.edit');

    // Subject Routes
    Route::apiResource('subjects', SubjectController::class)->only(['index', 'show']);
    Route::apiResource('subjects', SubjectController::class)->only(['store'])->middleware('permission:subjects.create');
    Route::apiResource('subjects', SubjectController::class)->only(['update'])->middleware('permission:subjects.edit');
    Route::apiResource('subjects', SubjectController::class)->only(['destroy'])->middleware('permission:subjects.delete');
    Route::get('subjects/by-grade/{grade}', [SubjectController::class, 'byGrade']);
    Route::get('subjects/by-department/{departmentId}', [SubjectController::class, 'byDepartment']);

    // Global people search (students + teachers/accountants/staff), branch-scoped
    Route::get('global-search/export', [GlobalSearchController::class, 'export'])
        ->middleware('permission:search.global');
    Route::get('global-search', [GlobalSearchController::class, 'index'])
        ->middleware('permission:search.global');

    // Teacher Routes
    Route::get('teachers', [TeacherController::class, 'index'])->middleware('permission:teachers.view');
    Route::post('teachers', [TeacherController::class, 'store'])->middleware('permission:teachers.create');
        Route::get('teachers/export', [TeacherController::class, 'export'])->middleware('permission:teachers.export');
    Route::get('teachers/{id}', [TeacherController::class, 'show'])->middleware('permission:teachers.view');
    Route::put('teachers/{id}', [TeacherController::class, 'update'])->middleware('permission:teachers.edit');
    Route::delete('teachers/{id}', [TeacherController::class, 'destroy'])->middleware('permission:teachers.delete');
    Route::post('teachers/{id}/restore', [TeacherController::class, 'restore'])->middleware('permission:teachers.delete');
    Route::post('teachers/{id}/upload-profile-picture', [TeacherController::class, 'uploadProfilePicture']);

    // Student Routes
    Route::get('students', [StudentController::class, 'index'])->middleware('permission:students.view');
    Route::post('students', [StudentController::class, 'store'])->middleware('permission:students.create');
        Route::get('students/export', [StudentController::class, 'export'])->middleware('permission:students.export');
    Route::get('students/by-user/{userId}', [StudentController::class, 'getByUserId']);
    Route::get('students/{studentUserId}/exam-progress-card.pdf', [\App\Http\Controllers\StudentExamProgressCardController::class, 'download']);
    Route::get('students/{studentUserId}/fee-statement.pdf', [\App\Http\Controllers\StudentFeeStatementController::class, 'download']);

    // Promotion routes - MUST come before students/{id} to avoid route conflicts
    Route::get('students/promotion-dashboard-stats', [StudentController::class, 'getPromotionDashboardStats'])->middleware('permission:students.promote');
    Route::get('students/promotion-batches', [StudentController::class, 'getPromotionBatches'])->middleware('permission:students.promote');
    Route::get('students/promotion-batches/{batchId}', [StudentController::class, 'getPromotionBatchDetails'])->middleware('permission:students.promote');
    Route::post('students/promotion-batches/{batchId}/revert', [StudentController::class, 'revertPromotionBatch'])->middleware('permission:students.promote');
    Route::post('students/promote', [StudentController::class, 'promote'])->middleware('permission:students.promote');
    Route::post('students/promote-with-fee-handling', [StudentController::class, 'promoteWithFeeHandling'])->middleware('permission:students.promote');
    Route::post('students/preview-promotion', [StudentController::class, 'previewPromotion'])->middleware('permission:students.promote');
    Route::post('students/revert-promotion', [StudentController::class, 'revertPromotion'])->middleware('permission:students.promote');
    Route::get('students/{id}/promotion-history', [StudentController::class, 'getPromotionHistory'])->middleware('permission:students.view');
    Route::get('students/{id}/dues', [StudentController::class, 'getStudentDues']);

    // Student CRUD routes with parameters
    Route::get('students/{id}', [StudentController::class, 'show'])->middleware('permission:students.view');
    Route::put('students/{id}', [StudentController::class, 'update'])->middleware('permission:students.edit');
    Route::delete('students/{id}', [StudentController::class, 'destroy'])->middleware('permission:students.delete');
    Route::post('students/{id}/restore', [StudentController::class, 'restore'])->middleware('permission:students.delete');
    Route::post('students/{id}/upload-profile-picture', [StudentController::class, 'uploadProfilePicture']);

    // Class & Section Routes - Full CRUD
    Route::prefix('classes')->group(function () {
        Route::get('/', [ClassController::class, 'index']);
        Route::post('/', [ClassController::class, 'store'])->middleware('permission:grades.create');
        Route::get('grades', [ClassController::class, 'getGrades']);
        Route::get('grade/{grade}/stats', [ClassController::class, 'getGradeStats']);
        Route::get('sections', [ClassController::class, 'getSections']);
        Route::get('{id}', [ClassController::class, 'show']);
        Route::put('{id}', [ClassController::class, 'update'])->middleware('permission:grades.edit');
        Route::delete('{id}', [ClassController::class, 'destroy'])->middleware('permission:grades.delete');
    });

    // Grade Routes - Full CRUD
    Route::get('grades/export', [GradeController::class, 'export'])->middleware('permission:grades.view');
    Route::apiResource('grades', GradeController::class)->only(['index', 'show'])->parameters([
        'grades' => 'value',
    ]);
    Route::apiResource('grades', GradeController::class)->only(['store'])->middleware('permission:grades.create')->parameters([
        'grades' => 'value',
    ]);
    Route::apiResource('grades', GradeController::class)->only(['update'])->middleware('permission:grades.edit')->parameters([
        'grades' => 'value',
    ]);
    Route::apiResource('grades', GradeController::class)->only(['destroy'])->middleware('permission:grades.delete')->parameters([
        'grades' => 'value',
    ]);

    // Student Group Routes
    Route::apiResource('student-groups', StudentGroupController::class)->only(['index', 'show']);
    Route::apiResource('student-groups', StudentGroupController::class)->only(['store'])->middleware('permission:groups.create');
    Route::apiResource('student-groups', StudentGroupController::class)->only(['update'])->middleware('permission:groups.edit');
    Route::apiResource('student-groups', StudentGroupController::class)->only(['destroy'])->middleware('permission:groups.delete');
    Route::post('student-groups/{id}/add-member', [StudentGroupController::class, 'addMember'])->middleware('permission:groups.edit');
    Route::delete('student-groups/{id}/members/{studentId}', [StudentGroupController::class, 'removeMember'])->middleware('permission:groups.edit');

    // Section Routes - Full CRUD
    Route::prefix('sections')->group(function () {
        Route::get('/', [SectionController::class, 'index']);
        Route::post('/', [SectionController::class, 'store'])->middleware('permission:sections.create');
        Route::get('export', [SectionController::class, 'export'])->middleware('permission:sections.export');
        Route::get('{id}/subjects', [SectionSubjectController::class, 'getSectionSubjects']);
        Route::get('{id}', [SectionController::class, 'show']);
        Route::put('{id}', [SectionController::class, 'update'])->middleware('permission:sections.edit');
        Route::delete('{id}', [SectionController::class, 'destroy'])->middleware('permission:sections.delete');
        Route::put('{id}/toggle-status', [SectionController::class, 'toggleStatus'])->middleware('permission:sections.edit');
    });

    // Section-Subject Assignment Routes
    Route::prefix('section-subjects')->group(function () {
        Route::get('/', [SectionSubjectController::class, 'index']);
        Route::post('/', [SectionSubjectController::class, 'assignSubject'])->middleware('permission:subjects.edit');
        Route::post('bulk', [SectionSubjectController::class, 'assignMultipleSubjects'])->middleware('permission:subjects.edit');
        Route::post('copy', [SectionSubjectController::class, 'copySubjects'])->middleware('permission:subjects.edit');
        Route::put('{id}', [SectionSubjectController::class, 'updateAssignment'])->middleware('permission:subjects.edit');
        Route::delete('{id}', [SectionSubjectController::class, 'removeSubject'])->middleware('permission:subjects.edit');
    });

    // Homework assignments (app + website)
    Route::prefix('assignments')->group(function () {
        Route::get('/', [AssignmentController::class, 'index']);
        Route::post('/', [AssignmentController::class, 'store'])->middleware('permission:assignments.create');
        Route::get('eligible-students', [AssignmentController::class, 'eligibleStudents']);
        Route::post('preview-recipients', [AssignmentController::class, 'previewRecipients'])->middleware('permission:assignments.create');
        Route::post('{id}/my-submission', [AssignmentController::class, 'storeMySubmission']);
        Route::get('{id}', [AssignmentController::class, 'show']);
        Route::put('{id}', [AssignmentController::class, 'update'])->middleware('permission:assignments.edit');
        Route::delete('{id}', [AssignmentController::class, 'destroy'])->middleware('permission:assignments.delete');
    });

    // Exam Routes
    Route::apiResource('exams', ExamController::class)->only(['index', 'show']);
    Route::apiResource('exams', ExamController::class)->only(['store'])->middleware('permission:exams.create');
    Route::apiResource('exams', ExamController::class)->only(['update'])->middleware('permission:exams.edit');
    Route::apiResource('exams', ExamController::class)->only(['destroy'])->middleware('permission:exams.delete');
    Route::get('exams/{id}/statistics', [ExamController::class, 'statistics'])->middleware('permission:exams.view');
    Route::post('exam-results', [ExamController::class, 'storeResult'])->middleware('permission:exams.results');
    Route::get('exams/{id}/results', [ExamController::class, 'getResults'])->middleware('permission:exams.view');
    Route::get('students/{studentId}/results', [ExamController::class, 'getStudentResults']);

    // Exam Terms
    Route::prefix('exam-terms')->group(function () {
        Route::get('/', [ExamTermController::class, 'index'])->middleware('permission:exams.view');
        Route::post('/', [ExamTermController::class, 'store'])->middleware('permission:exams.create');
        Route::get('{id}', [ExamTermController::class, 'show'])->middleware('permission:exams.view');
        Route::put('{id}', [ExamTermController::class, 'update'])->middleware('permission:exams.edit');
        Route::delete('{id}', [ExamTermController::class, 'destroy'])->middleware('permission:exams.delete');
    });

    // Exam Schedules
    Route::prefix('exam-schedules')->group(function () {
        Route::get('/', [ExamScheduleController::class, 'index']);
        Route::post('bulk', [ExamScheduleController::class, 'storeBulk'])->middleware('permission:exams.create');
        Route::post('/', [ExamScheduleController::class, 'store'])->middleware('permission:exams.create');
        Route::get('{id}/students', [ExamScheduleController::class, 'getStudents']);
        // No numeric constraint: IDs are opaque hashid strings when HASHIDS_ENABLED is on.
        Route::get('{id}', [ExamScheduleController::class, 'show']);
        Route::put('{id}', [ExamScheduleController::class, 'update'])->middleware('permission:exams.edit');
        Route::delete('{id}', [ExamScheduleController::class, 'destroy'])->middleware('permission:exams.delete');
    });

    // Exam Marks
    Route::prefix('exam-marks')->group(function () {
        Route::get('schedule/{scheduleId}', [ExamMarkController::class, 'getMarks']);
        Route::post('schedule/{scheduleId}', [ExamMarkController::class, 'storeMarks'])->middleware('permission:exams.results');
        Route::get('student/{studentId}/overview', [ExamMarkController::class, 'getStudentMarksOverview']);
        Route::get('student/{studentId}', [ExamMarkController::class, 'getStudentMarks']);
    });

    // Fee Routes
    // Fee Types Routes
    Route::get('fee-types', [FeeTypeController::class, 'index'])->middleware('permission:fees.view');
    Route::post('fee-types', [FeeTypeController::class, 'store'])->middleware('permission:fees.create');
    Route::get('fee-types/{id}', [FeeTypeController::class, 'show'])->middleware('permission:fees.view');
    Route::put('fee-types/{id}', [FeeTypeController::class, 'update'])->middleware('permission:fees.edit');
    Route::delete('fee-types/{id}', [FeeTypeController::class, 'destroy'])->middleware('permission:fees.delete');
    Route::put('fee-types/{id}/toggle-status', [FeeTypeController::class, 'toggleStatus'])->middleware('permission:fees.edit');

    Route::get('fee-structures', [FeeController::class, 'indexStructures'])->middleware('permission:fees.view');
    Route::post('fee-structures', [FeeController::class, 'storeStructure'])->middleware('permission:fees.create');
    Route::get('fee-structures/{id}', [FeeController::class, 'show'])->middleware('permission:fees.view');
    Route::put('fee-structures/{id}', [FeeController::class, 'updateStructure'])->middleware('permission:fees.edit');
    Route::delete('fee-structures/{id}', [FeeController::class, 'destroyStructure'])->middleware('permission:fees.delete');

    // Fee Payments Routes - Specific routes MUST come before parameterized routes
    Route::prefix('fee-payments')->group(function () {
        Route::get('today', [FeeController::class, 'getTodayPayments'])->middleware('permission:fees.view');
        Route::get('by-class', [FeeController::class, 'getStudentFeesByClass'])->middleware('permission:fees.view');
        Route::get('/', [FeeController::class, 'indexPayments'])->middleware('permission:fees.view');
        Route::post('/', [FeeController::class, 'recordPayment'])->middleware('permission:fees.collect');
        Route::get('{id}/receipt', [FeeController::class, 'downloadReceipt']);
        Route::get('{id}', [FeeController::class, 'showPayment'])->middleware('permission:fees.view');
    });
    // Students may read their own fees; other roles still need fees.view (enforced in the controller).
    Route::get('students/{studentId}/fees', [FeeController::class, 'getStudentFees']);

    // Fee Dues routes
    Route::apiResource('fee-dues', FeeDuesController::class)->only(['index', 'show'])->middleware('permission:fees.view');
    Route::apiResource('fee-dues', FeeDuesController::class)->only(['store'])->middleware('permission:fees.create');
    Route::apiResource('fee-dues', FeeDuesController::class)->only(['update'])->middleware('permission:fees.edit');
    Route::apiResource('fee-dues', FeeDuesController::class)->only(['destroy'])->middleware('permission:fees.delete');
    Route::get('fee-dues/student/{studentId}', [FeeDuesController::class, 'getStudentDues']);
    Route::post('fee-dues/{id}/apply-payment', [FeeDuesController::class, 'applyPayment'])->middleware('permission:fees.collect');
    Route::post('fee-dues/{id}/waive', [FeeDuesController::class, 'waiveDue'])->middleware('permission:fees.edit');
    Route::post('fee-dues/{id}/revert', [FeeDuesController::class, 'revertDue'])->middleware('permission:fees.edit');
    Route::put('fee-dues/{id}/inline-update', [FeeDuesController::class, 'updateDue'])->middleware('permission:fees.edit');
    Route::get('fee-dues/aging-analysis', [FeeDuesController::class, 'getAgingAnalysis'])->middleware('permission:fees.report');
    Route::get('fee-dues/overdue', [FeeDuesController::class, 'getOverdueFees'])->middleware('permission:fees.report');
    Route::get('fee-dues/by-type/{studentId}', [FeeDuesController::class, 'getDuesByFeeType'])->middleware('permission:fees.view');

    // Fee Reports routes
    Route::prefix('fee-reports')->middleware('permission:fees.report')->group(function () {
        Route::get('dues', [FeeReportController::class, 'duesReport']);
        Route::get('promotion-dues', [FeeReportController::class, 'promotionDuesReport']);
        Route::get('collection', [FeeReportController::class, 'collectionReport']);
        Route::get('overdue', [FeeReportController::class, 'overdueReport']);
        Route::get('student-statement/{studentId}', [FeeReportController::class, 'studentStatement']);
        Route::get('aging-analysis', [FeeReportController::class, 'agingAnalysis']);
    });

    // Real-time notifications
    Route::get('notifications/stream', [RealTimeNotificationController::class, 'streamNotifications']);

    // Attendance Routes (specific routes MUST come before apiResource)
    Route::prefix('attendance')->group(function () {
        Route::get('dashboard', [AttendanceController::class, 'getDashboard']);
        Route::get('export', [AttendanceController::class, 'export']);
        Route::get('class-status', [AttendanceController::class, 'getClassStatus']);
        Route::post('notify-students', [AttendanceController::class, 'notifyStudents'])->middleware('permission:notifications.create');
        Route::get('notify-receipts', [AttendanceController::class, 'notifyReceipts']);
        Route::post('bulk', [AttendanceController::class, 'markBulk'])->middleware('permission:student_attendance.mark');
        Route::get('report', [AttendanceController::class, 'getReport']);
        Route::get('student/{studentId}/overview', [AttendanceController::class, 'getStudentAttendanceOverview']);
        Route::get('student/{studentId}', [AttendanceController::class, 'getStudentAttendance']);
        Route::get('teacher/{teacherId}', [AttendanceController::class, 'getTeacherAttendance']);
        Route::get('class/{grade}/{section}', [AttendanceController::class, 'getClassAttendance']);
        Route::get('report/{studentId}', [AttendanceController::class, 'generateReport']);
    });
    // Param renamed to {id} so the DecodeHashids middleware (which only decodes
    // id/*Id/*_id route params) converts the hashid token back to an integer for
    // show/update/destroy. Without this the apiResource param {attendance} stays
    // a raw hashid and the controller's where('id', ...) misses → 404.
    Route::apiResource('attendance', AttendanceController::class)->only(['index', 'show'])->parameters(['attendance' => 'id']);
    Route::apiResource('attendance', AttendanceController::class)->only(['update'])->middleware('permission:student_attendance.edit')->parameters(['attendance' => 'id']);
    Route::apiResource('attendance', AttendanceController::class)->only(['destroy'])->middleware('permission:student_attendance.delete')->parameters(['attendance' => 'id']);
    Route::apiResource('attendance', AttendanceController::class)->only(['store'])->middleware('permission:student_attendance.mark')->parameters(['attendance' => 'id']);

    Route::get('id-cards/me', [IdCardController::class, 'me']);
    Route::get('id-cards', [IdCardController::class, 'index']);
    Route::post('id-cards/issue', [IdCardController::class, 'issue']);
    Route::post('id-cards/verify', [IdCardController::class, 'verify']);
    Route::get('id-cards/{id}/scans', [IdCardController::class, 'scans']);
    Route::get('id-cards/{id}/print.pdf', [IdCardController::class, 'print']);
    Route::post('id-cards/{id}/status', [IdCardController::class, 'updateStatus']);

    // Leave Routes (specific routes MUST come before apiResource)
    Route::get('leaves/student/{studentId}', [LeaveController::class, 'getStudentLeaves']);
    Route::get('leaves/teacher/{teacherId}', [LeaveController::class, 'getTeacherLeaves']);
    Route::apiResource('leaves', LeaveController::class)->only(['index', 'show']);
    // Own applications are allowed without leaves.create / leaves.edit. The controller
    // still requires those permissions to apply or change someone else's leave.
    Route::apiResource('leaves', LeaveController::class)->only(['store']);
    Route::apiResource('leaves', LeaveController::class)->only(['update']);
    Route::apiResource('leaves', LeaveController::class)->only(['destroy'])->middleware('permission:leaves.delete');

    // Library Routes (permission-gated; literal routes before {id} routes so
    // DecodeHashids resolves ids and there is no route conflict)
    Route::get('book-issues/active', [LibraryController::class, 'getActiveIssues'])->middleware('permission:library.view');
    Route::get('book-issues/overdue', [LibraryController::class, 'getOverdueIssues'])->middleware('permission:library.view');
    Route::post('book-issues/{id}/return', [LibraryController::class, 'returnBook'])->middleware('permission:library.return');
    Route::get('students/{studentId}/book-issues', [LibraryController::class, 'getStudentIssues'])->middleware('permission:library.view');

    Route::get('books', [LibraryController::class, 'index'])->middleware('permission:library.view');
    Route::post('books', [LibraryController::class, 'store'])->middleware('permission:library.create');
    Route::post('books/{id}/issue', [LibraryController::class, 'issueBook'])->middleware('permission:library.issue');
    Route::get('books/{id}/history', [LibraryController::class, 'getBookHistory'])->middleware('permission:library.view');
    Route::get('books/{id}', [LibraryController::class, 'show'])->middleware('permission:library.view');
    Route::put('books/{id}', [LibraryController::class, 'update'])->middleware('permission:library.edit');
    Route::delete('books/{id}', [LibraryController::class, 'destroy'])->middleware('permission:library.delete');

    // Enterprise Library Modular Routes
    Route::prefix('library')->middleware('permission:library.view')->group(function () {
        // 1. Dashboard
        Route::get('dashboard/summary', [LibraryDashboardController::class, 'getSummary']);
        Route::get('dashboard/trends', [LibraryDashboardController::class, 'getCirculationTrends']);
        Route::get('dashboard/popular-books', [LibraryDashboardController::class, 'getPopularBooks']);

        // 2. Catalog Masters
        Route::get('categories', [LibraryCatalogController::class, 'getCategories']);
        Route::post('categories', [LibraryCatalogController::class, 'storeCategory'])->middleware('permission:library.create');
        Route::put('categories/{id}', [LibraryCatalogController::class, 'updateCategory'])->middleware('permission:library.edit');
        Route::delete('categories/{id}', [LibraryCatalogController::class, 'destroyCategory'])->middleware('permission:library.delete');

        Route::get('authors', [LibraryCatalogController::class, 'getAuthors']);
        Route::post('authors', [LibraryCatalogController::class, 'storeAuthor'])->middleware('permission:library.create');
        Route::put('authors/{id}', [LibraryCatalogController::class, 'updateAuthor'])->middleware('permission:library.edit');
        Route::delete('authors/{id}', [LibraryCatalogController::class, 'destroyAuthor'])->middleware('permission:library.delete');

        Route::get('publishers', [LibraryCatalogController::class, 'getPublishers']);
        Route::post('publishers', [LibraryCatalogController::class, 'storePublisher'])->middleware('permission:library.create');
        Route::put('publishers/{id}', [LibraryCatalogController::class, 'updatePublisher'])->middleware('permission:library.edit');
        Route::delete('publishers/{id}', [LibraryCatalogController::class, 'destroyPublisher'])->middleware('permission:library.delete');

        Route::get('subjects', [LibraryCatalogController::class, 'getSubjects']);
        Route::post('subjects', [LibraryCatalogController::class, 'storeSubject'])->middleware('permission:library.create');
        Route::put('subjects/{id}', [LibraryCatalogController::class, 'updateSubject'])->middleware('permission:library.edit');
        Route::delete('subjects/{id}', [LibraryCatalogController::class, 'destroySubject'])->middleware('permission:library.delete');

        // 3. Inventory
        Route::get('copies', [LibraryInventoryController::class, 'getCopies']);
        Route::post('copies', [LibraryInventoryController::class, 'storeCopy'])->middleware('permission:library.create');
        Route::post('copies/batch-generate', [LibraryInventoryController::class, 'batchGenerateCopies'])->middleware('permission:library.create');
        Route::put('copies/{id}', [LibraryInventoryController::class, 'updateCopy'])->middleware('permission:library.edit');
        Route::delete('copies/{id}', [LibraryInventoryController::class, 'destroyCopy'])->middleware('permission:library.delete');

        Route::get('shelves', [LibraryInventoryController::class, 'getShelves']);
        Route::post('shelves', [LibraryInventoryController::class, 'storeShelf'])->middleware('permission:library.create');
        Route::put('shelves/{id}', [LibraryInventoryController::class, 'updateShelf'])->middleware('permission:library.edit');
        Route::delete('shelves/{id}', [LibraryInventoryController::class, 'destroyShelf'])->middleware('permission:library.delete');

        Route::get('stock-audits', [LibraryInventoryController::class, 'getStockAudits']);
        Route::post('stock-audits/start', [LibraryInventoryController::class, 'startStockAudit'])->middleware('permission:library.create');
        Route::post('stock-audits/{id}/scan', [LibraryInventoryController::class, 'scanAuditBarcode'])->middleware('permission:library.create');
        Route::post('stock-audits/{id}/complete', [LibraryInventoryController::class, 'completeStockAudit'])->middleware('permission:library.create');

        // 4. Members
        Route::get('members', [LibraryMemberController::class, 'getMembers']);
        Route::get('members/{id}', [LibraryMemberController::class, 'getMemberProfile']);

        // 5. Circulation
        Route::get('circulation/copy-lookup', [LibraryCirculationController::class, 'lookupCopies']);
        Route::post('circulation/issue', [LibraryCirculationController::class, 'issueBook'])->middleware('permission:library.issue');
        Route::post('circulation/return', [LibraryCirculationController::class, 'returnBook'])->middleware('permission:library.return');
        Route::post('circulation/renew', [LibraryCirculationController::class, 'renewBook'])->middleware('permission:library.issue');
        Route::get('circulation/overdue', [LibraryCirculationController::class, 'getOverdue']);
        Route::post('circulation/send-reminder', [LibraryCirculationController::class, 'sendOverdueReminder'])->middleware('permission:library.issue');

        // 6. Reservations
        Route::get('reservations', [LibraryReservationController::class, 'index']);
        Route::post('reservations', [LibraryReservationController::class, 'store'])->middleware('permission:library.create');
        Route::delete('reservations/{id}', [LibraryReservationController::class, 'cancel'])->middleware('permission:library.edit');
        Route::post('reservations/{id}/fulfill', [LibraryReservationController::class, 'fulfill'])->middleware('permission:library.edit');

        // 7. Fines
        Route::get('fines', [LibraryFineController::class, 'index']);
        Route::post('fines/{id}/collect', [LibraryFineController::class, 'collectPayment'])->middleware('permission:library.return');
        Route::post('fines/{id}/waive', [LibraryFineController::class, 'waiveFine'])->middleware('permission:library.edit');

        // 8. Procurement
        Route::get('procurements', [LibraryProcurementController::class, 'index']);
        Route::post('procurements', [LibraryProcurementController::class, 'store'])->middleware('permission:library.create');
        Route::get('procurements/{id}', [LibraryProcurementController::class, 'show']);
        Route::post('procurements/{id}/receive', [LibraryProcurementController::class, 'receiveAndAccession'])->middleware('permission:library.edit');

        // 9. Reports
        Route::get('reports/circulation', [LibraryReportController::class, 'circulationReport']);
        Route::get('reports/inventory', [LibraryReportController::class, 'inventoryReport']);
        Route::get('reports/fines', [LibraryReportController::class, 'fineReport']);
    });

    // Transport (Phase 1: management) — permission-gated; literal routes before {id} routes
    Route::get('transport-drivers', [TransportDriverController::class, 'index'])->middleware('permission:transport.view');
    Route::post('transport-drivers', [TransportDriverController::class, 'store'])->middleware('permission:transport.create');
    Route::get('transport-drivers/{id}', [TransportDriverController::class, 'show'])->middleware('permission:transport.view');
    Route::put('transport-drivers/{id}', [TransportDriverController::class, 'update'])->middleware('permission:transport.edit');
    Route::delete('transport-drivers/{id}', [TransportDriverController::class, 'destroy'])->middleware('permission:transport.delete');

    Route::get('vehicles', [VehicleController::class, 'index'])->middleware('permission:transport.view');
    Route::post('vehicles', [VehicleController::class, 'store'])->middleware('permission:transport.create');
    Route::get('vehicles/{id}', [VehicleController::class, 'show'])->middleware('permission:transport.view');
    Route::put('vehicles/{id}', [VehicleController::class, 'update'])->middleware('permission:transport.edit');
    Route::delete('vehicles/{id}', [VehicleController::class, 'destroy'])->middleware('permission:transport.delete');

    Route::get('transport-routes/{id}/stops', [TransportRouteController::class, 'getRouteStops'])->middleware('permission:transport.view');
    Route::get('transport-routes/{id}/students', [TransportRouteController::class, 'getRouteStudents'])->middleware('permission:transport.view');
    Route::get('transport-routes', [TransportRouteController::class, 'index'])->middleware('permission:transport.view');
    Route::post('transport-routes', [TransportRouteController::class, 'store'])->middleware('permission:transport.create');
    Route::get('transport-routes/{id}', [TransportRouteController::class, 'show'])->middleware('permission:transport.view');
    Route::put('transport-routes/{id}', [TransportRouteController::class, 'update'])->middleware('permission:transport.edit');
    Route::delete('transport-routes/{id}', [TransportRouteController::class, 'destroy'])->middleware('permission:transport.delete');

    Route::post('student-transport', [StudentTransportController::class, 'store'])->middleware('permission:transport.assign');
    Route::get('student-transport/{id}', [StudentTransportController::class, 'show'])->middleware('permission:transport.view');
    Route::put('student-transport/{id}', [StudentTransportController::class, 'update'])->middleware('permission:transport.assign');
    Route::delete('student-transport/{id}', [StudentTransportController::class, 'destroy'])->middleware('permission:transport.assign');

    // Upgraded Transport Enterprise Operations
    // 1. Dashboard
    Route::get('transport/dashboard', [TransportDashboardController::class, 'index'])->middleware('permission:transport.view');

    // 2. Stops Master
    Route::get('transport-stops', [TransportStopController::class, 'index'])->middleware('permission:transport.view');
    Route::post('transport-stops', [TransportStopController::class, 'store'])->middleware('permission:transport.create');
    Route::put('transport-stops/{id}', [TransportStopController::class, 'update'])->middleware('permission:transport.edit');
    Route::delete('transport-stops/{id}', [TransportStopController::class, 'destroy'])->middleware('permission:transport.delete');

    // 3. Assignments (Single & Bulk)
    Route::get('transport-assignments', [TransportAssignmentController::class, 'index'])->middleware('permission:transport.view');
    // Own/child-scoped inside the controller (students & parents have no transport.view)
    Route::get('transport/student/{studentUserId}/assignment', [TransportAssignmentController::class, 'forStudent']);
    Route::post('transport-assignments', [TransportAssignmentController::class, 'store'])->middleware('permission:transport.assign');
    Route::post('transport-assignments/bulk', [TransportAssignmentController::class, 'bulkAssign'])->middleware('permission:transport.assign');
    Route::put('transport-assignments/{id}', [TransportAssignmentController::class, 'update'])->middleware('permission:transport.assign');
    Route::delete('transport-assignments/{id}', [TransportAssignmentController::class, 'destroy'])->middleware('permission:transport.assign');

    // 4. Trips & Operations
    Route::get('transport-trips', [TransportTripController::class, 'index'])->middleware('permission:transport.view');
    Route::post('transport-trips', [TransportTripController::class, 'store'])->middleware('permission:transport.create');
    Route::get('transport-trips/{id}', [TransportTripController::class, 'show'])->middleware('permission:transport.view');
    Route::post('transport-trips/{id}/start', [TransportTripController::class, 'startTrip'])->middleware('permission:transport.edit');
    Route::post('transport-trips/{id}/complete', [TransportTripController::class, 'completeTrip'])->middleware('permission:transport.edit');
    Route::put('transport-trips/{id}/change-driver', [TransportTripController::class, 'changeDriver'])->middleware('permission:transport.edit');
    Route::put('transport-trips/{id}/change-vehicle', [TransportTripController::class, 'changeVehicle'])->middleware('permission:transport.edit');
    Route::delete('transport-trips/{id}', [TransportTripController::class, 'destroy'])->middleware('permission:transport.delete');

    // 5. Live Boarding & Drop
    Route::get('transport-trips/{id}/roster', [TransportBoardingController::class, 'getRoster'])->middleware('permission:transport.view');
    Route::post('transport-trips/{id}/board', [TransportBoardingController::class, 'markBoarded'])->middleware('permission:transport.edit');
    Route::post('transport-trips/{id}/unboard', [TransportBoardingController::class, 'unmarkBoarded'])->middleware('permission:transport.edit');
    Route::post('transport-trips/{id}/drop', [TransportBoardingController::class, 'markDropped'])->middleware('permission:transport.edit');
    Route::post('transport-trips/{id}/reach-stop', [TransportBoardingController::class, 'reachStop'])->middleware('permission:transport.edit');

    // 6. Live Tracking & ETA
    Route::post('transport-trips/{id}/gps', [TransportTrackingController::class, 'updateGps'])->middleware('permission:transport.edit');
    Route::get('transport-trips/{id}/live-tracking', [TransportTrackingController::class, 'getLiveTracking'])->middleware('permission:transport.view');
    Route::get('transport/student/{studentUserId}/tracking', [TransportTrackingController::class, 'getStudentTracking'])->middleware('permission:transport.view');

    // 7. Finance (Expenses, Fuel, Maintenance, Fees)
    Route::get('transport-expenses', [TransportFinanceController::class, 'getExpenses'])->middleware('permission:transport.view');
    Route::post('transport-expenses', [TransportFinanceController::class, 'storeExpense'])->middleware('permission:transport.create');
    Route::delete('transport-expenses/{id}', [TransportFinanceController::class, 'deleteExpense'])->middleware('permission:transport.delete');

    Route::get('transport-fuel', [TransportFinanceController::class, 'getFuelEntries'])->middleware('permission:transport.view');
    Route::post('transport-fuel', [TransportFinanceController::class, 'storeFuelEntry'])->middleware('permission:transport.create');
    Route::delete('transport-fuel/{id}', [TransportFinanceController::class, 'deleteFuelEntry'])->middleware('permission:transport.delete');

    Route::get('transport-maintenance', [TransportFinanceController::class, 'getMaintenanceLogs'])->middleware('permission:transport.view');
    Route::post('transport-maintenance', [TransportFinanceController::class, 'storeMaintenanceLog'])->middleware('permission:transport.create');
    Route::delete('transport-maintenance/{id}', [TransportFinanceController::class, 'deleteMaintenanceLog'])->middleware('permission:transport.delete');

    Route::get('transport-fees/summary', [TransportFinanceController::class, 'getFeesSummary'])->middleware('permission:transport.view');

    // Driver Portal & Duty Shift Routes (Dedicated endpoints for Driver role)
    Route::prefix('driver')->group(function () {
        Route::get('dashboard', [DriverPortalController::class, 'dashboard']);
        Route::get('shift/current', [DriverPortalController::class, 'currentShift']);
        Route::post('shift/start', [DriverPortalController::class, 'startShift']);
        Route::post('shift/stop', [DriverPortalController::class, 'stopShift']);
        Route::post('clock-in', [DriverPortalController::class, 'clockIn']);
        Route::post('clock-out', [DriverPortalController::class, 'clockOut']);
        Route::get('attendance-history', [DriverPortalController::class, 'attendanceHistory']);
        Route::post('trips/generate', [DriverPortalController::class, 'generateTrip']);
        Route::post('trips/{id}/location', [DriverPortalController::class, 'updateLocation']);
    });

    // Event Routes
    Route::apiResource('events', EventController::class)->only(['index', 'show']);
    Route::apiResource('events', EventController::class)->only(['store'])->middleware('permission:holidays.create');
    Route::apiResource('events', EventController::class)->only(['update'])->middleware('permission:holidays.edit');
    Route::apiResource('events', EventController::class)->only(['destroy'])->middleware('permission:holidays.delete');
    Route::get('events/upcoming', [EventController::class, 'getUpcoming']);
    Route::get('events/by-type/{type}', [EventController::class, 'getByType']);

    // OLD Holiday Routes - DISABLED (using HolidayController below instead)
    // Route::apiResource('holidays', EventController::class);
    // Route::get('holidays/year/{year}', [EventController::class, 'getHolidaysByYear']);

    // Timetable Routes. Literal paths stay before the resource so "class", "teacher-options", and "bulk-upsert" are not read as ids.
    Route::get('timetables/teacher-options', [TimetableController::class, 'teacherOptions'])->middleware('permission:timetable.view');
    Route::post('timetables/bulk-upsert', [TimetableController::class, 'bulkUpsert'])->middleware('permission:timetable.edit');
    Route::get('timetables/class/{grade}/{section}', [TimetableController::class, 'getByClass'])->middleware('permission:timetable.view');
    Route::delete('timetables/class/{grade}/{section}', [TimetableController::class, 'destroyClass'])->middleware('permission:timetable.delete');
    Route::apiResource('timetables', TimetableController::class)->only(['index', 'show'])->middleware('permission:timetable.view');
    Route::apiResource('timetables', TimetableController::class)->only(['store'])->middleware('permission:timetable.create');
    Route::apiResource('timetables', TimetableController::class)->only(['update'])->middleware('permission:timetable.edit');
    Route::apiResource('timetables', TimetableController::class)->only(['destroy'])->middleware('permission:timetable.delete');

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
        Route::get('dashboard', [AccountController::class, 'getDashboard'])->middleware('permission:accounts.view');
        Route::get('categories', [AccountController::class, 'getCategories'])->middleware('permission:accounts.view');
        Route::get('categories/{id}', [AccountController::class, 'getCategory'])->middleware('permission:accounts.view');
        Route::post('categories', [AccountController::class, 'createCategory'])->middleware('permission:accounts.create');
        Route::put('categories/{id}', [AccountController::class, 'updateCategory'])->middleware('permission:accounts.edit');
        Route::delete('categories/{id}', [AccountController::class, 'deleteCategory'])->middleware('permission:accounts.delete');
        Route::put('categories/{id}/toggle-status', [AccountController::class, 'toggleCategoryStatus'])->middleware('permission:accounts.edit');
    });

    // Transaction Routes - Full CRUD
    Route::get('transactions/export', [TransactionController::class, 'export'])->middleware('permission:transactions.view');
    Route::get('transactions/{id}/receipt', [TransactionController::class, 'downloadReceipt'])->middleware('permission:transactions.view');
    Route::apiResource('transactions', TransactionController::class)->only(['index', 'show'])->middleware('permission:transactions.view');
    Route::apiResource('transactions', TransactionController::class)->only(['store'])->middleware('permission:transactions.create');
    Route::apiResource('transactions', TransactionController::class)->only(['update'])->middleware('permission:transactions.edit');
    Route::apiResource('transactions', TransactionController::class)->only(['destroy'])->middleware('permission:transactions.delete');
    Route::post('transactions/{id}/approve', [TransactionController::class, 'approve'])->middleware('permission:transactions.approve');
    Route::post('transactions/{id}/reject', [TransactionController::class, 'reject'])->middleware('permission:transactions.reject');

    // Invoice Routes - Advanced with transaction integration
    Route::prefix('invoices')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->middleware('permission:invoices.view');
        Route::get('mine', [InvoiceController::class, 'mine']);
        Route::get('{id}/print.pdf', [InvoiceController::class, 'printPdf']);
        Route::post('/', [InvoiceController::class, 'store'])->middleware('permission:invoices.create');
        Route::get('stats', [InvoiceController::class, 'getStats'])->middleware('permission:invoices.view');
        Route::get('search-transactions', [InvoiceController::class, 'searchTransactions'])->middleware('permission:invoices.view');
        Route::post('generate-from-transactions', [InvoiceController::class, 'generateFromTransactions'])->middleware('permission:invoices.create');
        Route::get('{id}', [InvoiceController::class, 'show'])->middleware('permission:invoices.view');
        Route::put('{id}', [InvoiceController::class, 'update'])->middleware('permission:invoices.edit');
        Route::delete('{id}', [InvoiceController::class, 'destroy'])->middleware('permission:invoices.delete');
        Route::post('{id}/payment', [InvoiceController::class, 'recordPayment'])->middleware('permission:invoices.payment');
        Route::post('{id}/send', [InvoiceController::class, 'sendInvoice'])->middleware('permission:invoices.send');
    });

    // Holiday Routes - with role-based access
    Route::prefix('holidays')->group(function () {
        Route::get('/', [HolidayController::class, 'index']);
        Route::post('/', [HolidayController::class, 'store'])->middleware('permission:holidays.create');
        Route::get('export', [HolidayController::class, 'export']);
        Route::get('upcoming', [HolidayController::class, 'getUpcoming']);
        Route::get('calendar/{year}/{month}', [HolidayController::class, 'getCalendarData']);
        Route::get('{id}', [HolidayController::class, 'show']);
        Route::put('{id}', [HolidayController::class, 'update'])->middleware('permission:holidays.edit');
        Route::delete('{id}', [HolidayController::class, 'destroy'])->middleware('permission:holidays.delete');
    });

    // Settings Module Routes - User, Role & Permission Management
    // Users Management
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view');
        Route::get('/all', [UserController::class, 'all'])->middleware('permission:users.view');
        Route::get('export', [UserController::class, 'export'])->middleware('permission:users.view');
        Route::get('/{id}', [UserController::class, 'show'])->middleware('permission:users.view');
        Route::post('/', [UserController::class, 'store'])->middleware('permission:users.create');
        Route::put('/{id}', [UserController::class, 'update'])->middleware('permission:users.edit');
        Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
        Route::patch('/{id}/toggle-status', [UserController::class, 'toggleStatus'])->middleware('permission:users.edit');
        Route::post('/{id}/reset-password', [UserController::class, 'resetPassword'])->middleware('permission:users.edit');

        Route::get('/{id}/permissions', [UserController::class, 'getPermissions'])->middleware('permission:users.view');
        Route::post('/{id}/permissions', [UserController::class, 'updatePermissions'])->middleware('permission:users.edit');
        Route::post('/{id}/roles', [UserController::class, 'assignRoles'])->middleware('permission:users.edit');
    });

    // Academic Years Management
    Route::prefix('academic-years')->group(function () {
        Route::get('/', [AcademicYearController::class, 'index']);
        Route::get('/current', [AcademicYearController::class, 'current']);
        Route::post('/', [AcademicYearController::class, 'store'])->middleware('permission:settings.edit');
        Route::get('/{id}', [AcademicYearController::class, 'show']);
        Route::put('/{id}', [AcademicYearController::class, 'update'])->middleware('permission:settings.edit');
        Route::delete('/{id}', [AcademicYearController::class, 'destroy'])->middleware('permission:settings.edit');
    });

    // Enterprise Audit Logs (SuperAdmin, BranchAdmin & audit_logs.view)
    Route::prefix('audit-logs')->group(function () {
        Route::get('/stats', [AuditLogController::class, 'stats']);
        Route::get('/filters', [AuditLogController::class, 'filters']);
        Route::get('/export', [AuditLogController::class, 'export']);
        Route::get('/', [AuditLogController::class, 'index']);
        Route::get('/{id}', [AuditLogController::class, 'show']);
    });

    // Roles Management
    Route::prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/all', [RoleController::class, 'all']);
        Route::get('/{id}', [RoleController::class, 'show']);
        Route::post('/', [RoleController::class, 'store'])->middleware('permission:roles.create');
        Route::put('/{id}', [RoleController::class, 'update'])->middleware('permission:roles.edit');
        Route::delete('/{id}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');
        Route::post('/{id}/permissions', [RoleController::class, 'assignPermissions'])->middleware('permission:roles.edit');
        Route::get('/{id}/permissions', [RoleController::class, 'permissions']);
    });

    // Permissions Management - Combined routes
    Route::prefix('permissions')->group(function () {
        // Settings Module - CRUD operations
        Route::get('/', [PermissionManagementController::class, 'index']);
        Route::get('/all', [PermissionManagementController::class, 'all']);
        Route::get('/by-module', [PermissionManagementController::class, 'byModule']);
        Route::post('/', [PermissionManagementController::class, 'store'])->middleware('role:SuperAdmin,BranchAdmin');

        // Legacy permission controller endpoints - MUST be before /{id} routes
        Route::get('/roles', [App\Http\Controllers\PermissionController::class, 'getRoles']);
        Route::get('/modules', [App\Http\Controllers\PermissionController::class, 'getModules']);
        Route::get('/list', [App\Http\Controllers\PermissionController::class, 'getPermissions']);
        Route::get('/user/{id}/permissions', [App\Http\Controllers\PermissionController::class, 'getUserPermissions']);

        // Details endpoint for settings - MUST be after specific routes
        Route::get('/{id}', [PermissionManagementController::class, 'show']);
        Route::put('/{id}', [PermissionManagementController::class, 'update'])->middleware('role:SuperAdmin,BranchAdmin');
        Route::delete('/{id}', [PermissionManagementController::class, 'destroy'])->middleware('role:SuperAdmin,BranchAdmin');

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
        Route::post('/', [ModuleController::class, 'store'])->middleware('role:SuperAdmin,BranchAdmin');
        Route::put('/{id}', [ModuleController::class, 'update'])->middleware('role:SuperAdmin,BranchAdmin');
        Route::delete('/{id}', [ModuleController::class, 'destroy'])->middleware('role:SuperAdmin,BranchAdmin');
    });

    // Import Module - Data Import System (Rate limited - 120 per minute)
    Route::prefix('imports')->middleware('throttle:120,1')->group(function () {
        Route::get('/modules', [App\Http\Controllers\ImportController::class, 'getModules'])->middleware('permission:import.view');
        Route::get('/history', [App\Http\Controllers\ImportController::class, 'history'])->middleware('permission:import.view');
        Route::get('/batch/{batchId}', [App\Http\Controllers\ImportController::class, 'getBatchDetails'])->middleware('permission:import.view');
        Route::get('/template/{entity}', [App\Http\Controllers\ImportController::class, 'downloadTemplate'])->middleware('permission:import.view');

        Route::post('/{entity}/upload', [App\Http\Controllers\ImportController::class, 'upload'])->middleware('permission:import.upload');
        Route::post('/{entity}/validate/{batchId}', [App\Http\Controllers\ImportController::class, 'validate'])->middleware('permission:import.validate');
        Route::get('/{entity}/preview/{batchId}', [App\Http\Controllers\ImportController::class, 'preview'])->middleware('permission:import.view');
        Route::get('/{entity}/failed-export/{batchId}', [App\Http\Controllers\ImportController::class, 'exportFailedRows'])->middleware('permission:import.view');
        Route::patch('/{entity}/record/{id}', [App\Http\Controllers\ImportController::class, 'updateRecord'])->middleware('permission:import.validate');
        Route::post('/{entity}/batch/{batchId}/set-role', [App\Http\Controllers\ImportController::class, 'setBatchRole'])->middleware('permission:import.commit');
        Route::post('/{entity}/commit/{batchId}', [App\Http\Controllers\ImportController::class, 'commit'])->middleware('permission:import.commit');
        Route::post('/{entity}/revert/{batchId}', [App\Http\Controllers\ImportController::class, 'revert'])->middleware('permission:import.commit');
        Route::post('/revert-requests/{batchId}/request', [App\Http\Controllers\ImportController::class, 'requestRevert'])->middleware('permission:import.commit');
        Route::get('/revert-requests/pending', [App\Http\Controllers\ImportController::class, 'getPendingRevertRequests'])->middleware('permission:import.view');
        Route::post('/revert-requests/{batchId}/approve', [App\Http\Controllers\ImportController::class, 'approveRevert'])->middleware('permission:import.commit');
        Route::post('/revert-requests/{batchId}/reject', [App\Http\Controllers\ImportController::class, 'rejectRevert'])->middleware('permission:import.commit');
        Route::delete('/{entity}/cancel/{batchId}', [App\Http\Controllers\ImportController::class, 'cancel'])->middleware('permission:import.cancel');
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
        Route::get('/', [AdmissionController::class, 'index'])->middleware('permission:admissions.view');
        Route::get('/dashboard', [AdmissionController::class, 'getDashboard'])->middleware('permission:admissions.view');
        Route::post('/', [AdmissionController::class, 'store'])->middleware('permission:admissions.create');
        Route::get('/export', [AdmissionController::class, 'export'])->middleware('permission:admissions.export');
        Route::get('/{id}', [AdmissionController::class, 'show'])->middleware('permission:admissions.view');
        Route::put('/{id}', [AdmissionController::class, 'update'])->middleware('permission:admissions.edit');
        Route::delete('/{id}', [AdmissionController::class, 'destroy'])->middleware('permission:admissions.delete');
        Route::post('/{id}/update-status', [AdmissionController::class, 'updateStatus'])->middleware('permission:admissions.edit');
        Route::post('/{id}/convert-to-student', [AdmissionController::class, 'convertToStudent'])->middleware('permission:admissions.edit');
    });

    // Communication System Routes
    Route::prefix('communications')->group(function () {
        // Personal inbox (auth only — scoped to current user in CommunicationController)
        Route::get('/notifications/unread-count', [CommunicationController::class, 'unreadNotificationCount']);
        Route::get('/notifications/sent', [CommunicationController::class, 'getSentNotifications'])
            ->middleware('permission:notifications.view');
        Route::get('/notifications/receipts', [CommunicationController::class, 'getNotificationReceipts'])
            ->middleware('permission:notifications.view');
        Route::get('/notifications', [CommunicationController::class, 'getNotifications']);
        Route::get('/notifications/{id}', [CommunicationController::class, 'getNotification']);
        Route::post('/notifications/read-all', [CommunicationController::class, 'markAllAsRead']);
        Route::post('/notifications/{id}/read', [CommunicationController::class, 'markAsRead']);
        Route::post('/notifications', [CommunicationController::class, 'createNotification'])
            ->middleware('permission:notifications.create');
        Route::post('/notifications/broadcast', [CommunicationController::class, 'broadcastNotification'])
            ->middleware('permission:notifications.create');
        Route::get('/notifications/compose/staff-recipient-options', [CommunicationController::class, 'composeStaffRecipientOptions'])
            ->middleware('permission:notifications.create');

        Route::get('/notification-campaigns/modules', [NotificationCampaignController::class, 'modules'])
            ->middleware('permission:notifications.view');
        Route::get('/notification-campaigns/dashboard', [NotificationCampaignController::class, 'dashboard'])
            ->middleware('permission:notifications.view');
        Route::get('/notification-campaigns/templates', [NotificationCampaignController::class, 'templates'])
            ->middleware('permission:notifications.view');
        Route::get('/notification-campaigns/marked-attendance', [NotificationCampaignController::class, 'markedAttendance'])
            ->middleware('permission:notifications.view');
        Route::get('/notification-campaigns/class-options', [NotificationCampaignController::class, 'classOptions'])
            ->middleware('permission:notifications.view');
        Route::get('/notification-campaigns/fees-due-notify-meta', [NotificationCampaignController::class, 'feesDueNotifyMeta'])
            ->middleware('permission:notifications.view');
        Route::get('/notification-campaigns/staff-recipient-options', [NotificationCampaignController::class, 'staffRecipientOptions'])
            ->middleware('permission:notifications.create');
        Route::get('/notification-campaigns/eligible-targets', [NotificationCampaignController::class, 'eligibleTargets'])
            ->middleware('permission:notifications.view');
        Route::get('/notification-campaigns/section-delivery-status', [NotificationCampaignController::class, 'sectionDeliveryStatus'])
            ->middleware('permission:notifications.view');
        Route::post('/notification-campaigns/preview', [NotificationCampaignController::class, 'preview'])
            ->middleware('permission:notifications.create');
        Route::post('/notification-campaigns/notifications/{notificationId}/like', [NotificationCampaignController::class, 'like']);
        Route::get('/notification-campaigns', [NotificationCampaignController::class, 'index'])
            ->middleware('permission:notifications.view');
        Route::post('/notification-campaigns', [NotificationCampaignController::class, 'store'])
            ->middleware('permission:notifications.create');
        Route::get('/notification-campaigns/{id}/progress', [NotificationCampaignController::class, 'progress'])
            ->middleware('permission:notifications.view');
        Route::get('/notification-campaigns/{id}/recipients', [NotificationCampaignController::class, 'recipients'])
            ->middleware('permission:notifications.view');
        Route::post('/notification-campaigns/{id}/retry-failed', [NotificationCampaignController::class, 'retryFailed'])
            ->middleware('permission:notifications.edit');
        Route::get('/notification-campaigns/{id}', [NotificationCampaignController::class, 'show'])
            ->middleware('permission:notifications.view');

        // Announcements
        Route::get('/announcements', [CommunicationController::class, 'getAnnouncements']);
        Route::post('/announcements', [CommunicationController::class, 'createAnnouncement'])->middleware('permission:notifications.create');
        Route::get('/announcements/{id}', [CommunicationController::class, 'getAnnouncement']);
        Route::put('/announcements/{id}', [CommunicationController::class, 'updateAnnouncement'])->middleware('permission:notifications.edit');
        Route::delete('/announcements/{id}', [CommunicationController::class, 'deleteAnnouncement'])->middleware('permission:notifications.delete');

        // Circulars
        Route::get('/circulars', [CommunicationController::class, 'getCirculars']);
        Route::post('/circulars', [CommunicationController::class, 'createCircular'])->middleware('permission:notifications.create');
        Route::get('/circulars/{id}', [CommunicationController::class, 'getCircular']);
        Route::put('/circulars/{id}', [CommunicationController::class, 'updateCircular'])->middleware('permission:notifications.edit');
        Route::delete('/circulars/{id}', [CommunicationController::class, 'deleteCircular'])->middleware('permission:notifications.delete');
        Route::post('/circulars/{id}/acknowledge', [CommunicationController::class, 'acknowledgeCircular']);
    });

    // Message Sending Routes (WhatsApp & SMS)
    Route::middleware('permission:notifications.create')->group(function () {
        Route::post('/send-whatsapp', [MessageController::class, 'sendWhatsApp']);
        Route::post('/send-sms', [MessageController::class, 'sendSms']);
        Route::post('/send-both', [MessageController::class, 'sendBoth']);
    });
});
