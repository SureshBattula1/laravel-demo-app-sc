<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * teacher_attendance.status used legacy values (HalfDay, OnLeave) while the app
     * sends the same labels as student attendance (Half-Day, Sick Leave, Leave).
     */
    public function up(): void
    {
        if (! Schema::hasTable('teacher_attendance')) {
            return;
        }

        // Step 1: widen ENUM so we can store both legacy and new values during migration
        DB::statement("ALTER TABLE teacher_attendance MODIFY COLUMN status ENUM(
            'Present',
            'Absent',
            'Late',
            'HalfDay',
            'OnLeave',
            'Half-Day',
            'Sick Leave',
            'Leave'
        ) NOT NULL DEFAULT 'Present'");

        // Step 2: normalize legacy rows to app-wide labels
        DB::table('teacher_attendance')->where('status', 'HalfDay')->update(['status' => 'Half-Day']);
        DB::table('teacher_attendance')->where('status', 'OnLeave')->update(['status' => 'Leave']);

        // Step 3: final ENUM aligned with student_attendance / markBulk validation
        DB::statement("ALTER TABLE teacher_attendance MODIFY COLUMN status ENUM(
            'Present',
            'Absent',
            'Late',
            'Half-Day',
            'Sick Leave',
            'Leave'
        ) NOT NULL DEFAULT 'Present'");
    }

    /**
     * Restore previous ENUM (may truncate rows that use values not in old set).
     */
    public function down(): void
    {
        if (! Schema::hasTable('teacher_attendance')) {
            return;
        }

        DB::statement("ALTER TABLE teacher_attendance MODIFY COLUMN status ENUM(
            'Present',
            'Absent',
            'Late',
            'HalfDay',
            'OnLeave',
            'Half-Day',
            'Sick Leave',
            'Leave'
        ) NOT NULL DEFAULT 'Present'");

        DB::table('teacher_attendance')->where('status', 'Half-Day')->update(['status' => 'HalfDay']);
        DB::table('teacher_attendance')->whereIn('status', ['Sick Leave', 'Leave'])->update(['status' => 'OnLeave']);

        DB::statement("ALTER TABLE teacher_attendance MODIFY COLUMN status ENUM(
            'Present',
            'Absent',
            'Late',
            'HalfDay',
            'OnLeave'
        ) NOT NULL DEFAULT 'Present'");
    }
};
