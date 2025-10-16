<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ADMISSION MANAGEMENT
        
        if (!Schema::hasTable('admission_applications')) {
            Schema::create('admission_applications', function (Blueprint $table) {
                $table->id();
                $table->string('application_number')->unique();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->string('academic_year');
                $table->string('applying_for_grade');
                $table->string('student_first_name');
                $table->string('student_last_name');
                $table->date('date_of_birth');
                $table->enum('gender', ['Male', 'Female', 'Other']);
                $table->string('email');
                $table->string('phone', 20);
                $table->string('father_name');
                $table->string('mother_name');
                $table->enum('application_status', ['Applied', 'UnderReview', 'Admitted', 'Rejected'])->default('Applied');
                $table->timestamps();
                
                $table->index(['branch_id', 'academic_year']);
            });
        }

        if (!Schema::hasTable('admission_enquiries')) {
            Schema::create('admission_enquiries', function (Blueprint $table) {
                $table->id();
                $table->string('enquiry_number')->unique();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->string('student_name');
                $table->string('parent_phone', 20);
                $table->string('interested_grade');
                $table->date('enquiry_date');
                $table->enum('status', ['Open', 'FollowedUp', 'Converted', 'Closed'])->default('Open');
                $table->timestamps();
            });
        }

        // ATTENDANCE ENHANCEMENTS
        
        if (!Schema::hasTable('student_attendance')) {
            Schema::create('student_attendance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->date('attendance_date');
                $table->enum('status', ['Present', 'Absent', 'Late', 'HalfDay', 'OnLeave'])->default('Present');
                $table->time('check_in_time')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
                
                $table->unique(['student_id', 'attendance_date']);
            });
        }

        if (!Schema::hasTable('teacher_attendance')) {
            Schema::create('teacher_attendance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
                $table->date('attendance_date');
                $table->enum('status', ['Present', 'Absent', 'HalfDay', 'OnLeave'])->default('Present');
                $table->time('check_in_time')->nullable();
                $table->time('check_out_time')->nullable();
                $table->timestamps();
                
                $table->unique(['teacher_id', 'attendance_date']);
            });
        }

        // SALARY MANAGEMENT
        
        if (!Schema::hasTable('salaries')) {
            Schema::create('salaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->integer('salary_month');
                $table->integer('salary_year');
                $table->decimal('basic_salary', 10, 2);
                $table->json('allowances')->nullable();
                $table->decimal('total_allowances', 10, 2)->default(0);
                $table->json('deductions')->nullable();
                $table->decimal('total_deductions', 10, 2)->default(0);
                $table->decimal('gross_salary', 10, 2);
                $table->decimal('net_salary', 10, 2);
                $table->date('payment_date')->nullable();
                $table->enum('status', ['Draft', 'Approved', 'Paid'])->default('Draft');
                $table->timestamps();
                
                $table->unique(['employee_id', 'salary_month', 'salary_year']);
            });
        }

        // LEAVE MANAGEMENT
        
        if (!Schema::hasTable('leave_types')) {
            Schema::create('leave_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->enum('applicable_for', ['Student', 'Teacher', 'Staff', 'All'])->default('All');
                $table->integer('max_days_per_year');
                $table->boolean('requires_approval')->default(true);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('leave_applications')) {
            Schema::create('leave_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('leave_type_id')->constrained('leave_types')->onDelete('restrict');
                $table->date('from_date');
                $table->date('to_date');
                $table->integer('total_days');
                $table->text('reason');
                $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
                $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('leave_balance')) {
            Schema::create('leave_balance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('leave_type_id')->constrained('leave_types')->onDelete('cascade');
                $table->string('academic_year');
                $table->integer('total_allowed');
                $table->integer('used')->default(0);
                $table->integer('balance');
                $table->timestamps();
                
                $table->unique(['user_id', 'leave_type_id', 'academic_year']);
            });
        }

        // EXAM & ASSESSMENT
        
        if (!Schema::hasTable('exam_schedules')) {
            Schema::create('exam_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
                $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
                $table->string('grade');
                $table->date('exam_date');
                $table->time('start_time');
                $table->time('end_time');
                $table->decimal('total_marks', 8, 2);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('grade_systems')) {
            Schema::create('grade_systems', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->string('grade_name', 10);
                $table->decimal('min_percentage', 5, 2);
                $table->decimal('max_percentage', 5, 2);
                $table->decimal('grade_point', 4, 2)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('report_cards')) {
            Schema::create('report_cards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
                $table->string('academic_year');
                $table->decimal('total_marks_obtained', 10, 2);
                $table->decimal('percentage', 5, 2);
                $table->string('grade_obtained', 10)->nullable();
                $table->integer('rank_in_class')->nullable();
                $table->text('teacher_remarks')->nullable();
                $table->boolean('is_published')->default(false);
                $table->timestamps();
                
                $table->unique(['student_id', 'exam_id']);
            });
        }

        // HOMEWORK & LMS
        
        if (!Schema::hasTable('homework_assignments')) {
            Schema::create('homework_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
                $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
                $table->string('title');
                $table->text('description');
                $table->string('grade');
                $table->string('section')->nullable();
                $table->decimal('total_marks', 8, 2);
                $table->date('issue_date');
                $table->date('due_date');
                $table->enum('status', ['Published', 'Closed'])->default('Published');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('homework_submissions')) {
            Schema::create('homework_submissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assignment_id')->constrained('homework_assignments')->onDelete('cascade');
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->timestamp('submission_date');
                $table->boolean('is_late')->default(false);
                $table->text('content')->nullable();
                $table->decimal('marks_obtained', 8, 2)->nullable();
                $table->text('feedback')->nullable();
                $table->enum('status', ['Submitted', 'Graded'])->default('Submitted');
                $table->timestamps();
                
                $table->unique(['assignment_id', 'student_id']);
            });
        }

        if (!Schema::hasTable('online_tests')) {
            Schema::create('online_tests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
                $table->string('title');
                $table->string('grade');
                $table->integer('duration_minutes');
                $table->decimal('total_marks', 8, 2);
                $table->datetime('start_time');
                $table->datetime('end_time');
                $table->enum('status', ['Draft', 'Published', 'Completed'])->default('Draft');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('test_questions')) {
            Schema::create('test_questions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('test_id')->constrained('online_tests')->onDelete('cascade');
                $table->text('question_text');
                $table->enum('question_type', ['MCQ', 'TrueFalse', 'Descriptive'])->default('MCQ');
                $table->json('options')->nullable();
                $table->json('correct_answer');
                $table->decimal('marks', 5, 2);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('study_materials')) {
            Schema::create('study_materials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
                $table->string('title');
                $table->string('grade');
                $table->string('file_path')->nullable();
                $table->boolean('is_published')->default(false);
                $table->timestamps();
            });
        }

        // TIMETABLE
        
        if (!Schema::hasTable('timetable_periods')) {
            Schema::create('timetable_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->string('grade');
                $table->string('section');
                $table->enum('day_of_week', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']);
                $table->integer('period_number');
                $table->time('start_time');
                $table->time('end_time');
                $table->foreignId('subject_id')->nullable()->constrained('subjects')->onDelete('set null');
                $table->foreignId('teacher_id')->nullable()->constrained('teachers')->onDelete('set null');
                $table->string('room_number')->nullable();
                $table->boolean('is_break')->default(false);
                $table->timestamps();
            });
        }

        // TRANSPORT
        
        if (!Schema::hasTable('transport_stops')) {
            Schema::create('transport_stops', function (Blueprint $table) {
                $table->id();
                $table->foreignId('route_id')->constrained('transport_routes')->onDelete('cascade');
                $table->string('stop_name');
                $table->integer('stop_order');
                $table->time('arrival_time');
                $table->decimal('monthly_fee', 8, 2);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('student_transport')) {
            Schema::create('student_transport', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->foreignId('route_id')->constrained('transport_routes')->onDelete('cascade');
                $table->foreignId('stop_id')->constrained('transport_stops')->onDelete('cascade');
                $table->string('academic_year');
                $table->decimal('monthly_fee', 8, 2);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // HOSTEL
        
        if (!Schema::hasTable('hostels')) {
            Schema::create('hostels', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->string('name');
                $table->string('code')->unique();
                $table->enum('hostel_type', ['Boys', 'Girls', 'Mixed'])->default('Boys');
                $table->foreignId('warden_id')->nullable()->constrained('teachers')->onDelete('set null');
                $table->integer('total_capacity')->default(0);
                $table->integer('current_occupancy')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('hostel_rooms')) {
            Schema::create('hostel_rooms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('hostel_id')->constrained('hostels')->onDelete('cascade');
                $table->string('room_number');
                $table->integer('capacity');
                $table->integer('current_occupancy')->default(0);
                $table->decimal('monthly_fee', 10, 2);
                $table->boolean('is_available')->default(true);
                $table->timestamps();
                
                $table->unique(['hostel_id', 'room_number']);
            });
        }

        if (!Schema::hasTable('hostel_allocations')) {
            Schema::create('hostel_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->foreignId('room_id')->constrained('hostel_rooms')->onDelete('restrict');
                $table->string('academic_year');
                $table->date('allocation_date');
                $table->decimal('monthly_fee', 10, 2);
                $table->enum('status', ['Allocated', 'CheckedOut'])->default('Allocated');
                $table->timestamps();
            });
        }

        // LIBRARY ENHANCEMENTS
        
        if (!Schema::hasTable('book_categories')) {
            Schema::create('book_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('library_cards')) {
            Schema::create('library_cards', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('card_number')->unique();
                $table->date('issue_date');
                $table->date('expiry_date');
                $table->integer('max_books_allowed')->default(3);
                $table->enum('card_status', ['Active', 'Suspended', 'Expired'])->default('Active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('library_fines')) {
            Schema::create('library_fines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('book_issue_id')->constrained('book_issues')->onDelete('cascade');
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->decimal('fine_amount', 10, 2);
                $table->decimal('amount_paid', 10, 2)->default(0);
                $table->enum('payment_status', ['Pending', 'Paid'])->default('Pending');
                $table->timestamps();
            });
        }

        // COMMUNICATION
        
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('message');
                $table->enum('target_audience', ['All', 'Students', 'Teachers', 'Parents'])->default('All');
                $table->enum('priority', ['Low', 'Normal', 'High', 'Urgent'])->default('Normal');
                $table->timestamp('scheduled_time')->nullable();
                $table->enum('status', ['Draft', 'Sent'])->default('Draft');
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sms_logs')) {
            Schema::create('sms_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('notification_id')->nullable()->constrained('notifications')->onDelete('set null');
                $table->string('phone_number', 20);
                $table->text('message');
                $table->enum('status', ['Sent', 'Failed'])->default('Sent');
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('email_logs')) {
            Schema::create('email_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('notification_id')->nullable()->constrained('notifications')->onDelete('set null');
                $table->string('email_address');
                $table->string('subject');
                $table->text('body');
                $table->enum('status', ['Sent', 'Failed'])->default('Sent');
                $table->timestamp('sent_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('announcements')) {
            Schema::create('announcements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('cascade');
                $table->string('title');
                $table->text('content');
                $table->date('valid_from');
                $table->date('valid_to')->nullable();
                $table->boolean('is_published')->default(false);
                $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
                $table->timestamps();
            });
        }

        // CERTIFICATES
        
        if (!Schema::hasTable('certificate_templates')) {
            Schema::create('certificate_templates', function (Blueprint $table) {
                $table->id();
                $table->string('template_name');
                $table->enum('certificate_type', ['Bonafide', 'Transfer', 'Character', 'FeePayment', 'Other']);
                $table->text('template_content');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('generated_certificates')) {
            Schema::create('generated_certificates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('template_id')->constrained('certificate_templates')->onDelete('restrict');
                $table->string('certificate_number')->unique();
                $table->date('issue_date');
                $table->string('pdf_path')->nullable();
                $table->timestamps();
            });
        }

        // INVENTORY
        
        if (!Schema::hasTable('inventory_categories')) {
            Schema::create('inventory_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('inventory_items')) {
            Schema::create('inventory_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->foreignId('category_id')->constrained('inventory_categories')->onDelete('restrict');
                $table->string('item_name');
                $table->string('item_code')->unique();
                $table->integer('current_stock')->default(0);
                $table->decimal('unit_price', 10, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // PERMISSIONS
        
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('module');
                $table->string('action');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table) {
                $table->id();
                $table->string('role');
                $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
                $table->timestamps();
                
                $table->unique(['role', 'permission_id']);
            });
        }

        // FEEDBACK & COMPLAINTS
        
        if (!Schema::hasTable('complaints')) {
            Schema::create('complaints', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('subject');
                $table->text('description');
                $table->enum('status', ['Open', 'Resolved', 'Closed'])->default('Open');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('feedback')) {
            Schema::create('feedback', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('teacher_id')->nullable()->constrained('teachers')->onDelete('set null');
                $table->decimal('rating', 3, 2);
                $table->text('comments')->nullable();
                $table->timestamps();
            });
        }

        // SYSTEM
        
        if (!Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('setting_key')->unique();
                $table->text('setting_value');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('visitors')) {
            Schema::create('visitors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->string('visitor_name');
                $table->string('visitor_phone', 20);
                $table->string('purpose_of_visit');
                $table->datetime('check_in_time');
                $table->datetime('check_out_time')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('visitors');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('feedback');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('inventory_categories');
        Schema::dropIfExists('generated_certificates');
        Schema::dropIfExists('certificate_templates');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('study_materials');
        Schema::dropIfExists('test_questions');
        Schema::dropIfExists('online_tests');
        Schema::dropIfExists('homework_submissions');
        Schema::dropIfExists('homework_assignments');
        Schema::dropIfExists('report_cards');
        Schema::dropIfExists('grade_systems');
        Schema::dropIfExists('exam_schedules');
        Schema::dropIfExists('leave_balance');
        Schema::dropIfExists('leave_applications');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('salaries');
        Schema::dropIfExists('teacher_attendance');
        Schema::dropIfExists('student_attendance');
        Schema::dropIfExists('admission_enquiries');
        Schema::dropIfExists('admission_applications');
        Schema::dropIfExists('student_transport');
        Schema::dropIfExists('transport_stops');
        Schema::dropIfExists('hostel_allocations');
        Schema::dropIfExists('hostel_rooms');
        Schema::dropIfExists('hostels');
        Schema::dropIfExists('library_fines');
        Schema::dropIfExists('library_cards');
        Schema::dropIfExists('book_categories');
        Schema::dropIfExists('student_siblings');
        Schema::dropIfExists('student_health_records');
        Schema::dropIfExists('student_achievements');
        Schema::dropIfExists('class_upgrades');
        Schema::dropIfExists('student_group_members');
        Schema::dropIfExists('student_groups');
        Schema::dropIfExists('student_fees');
        Schema::dropIfExists('fee_discounts');
        Schema::dropIfExists('fee_installments');
        Schema::dropIfExists('fee_types');
    }
};

