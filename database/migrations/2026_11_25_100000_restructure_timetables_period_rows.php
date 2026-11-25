<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('timetables')) {
            $this->createPeriodTable();

            return;
        }

        if (Schema::hasColumn('timetables', 'schedule')) {
            Schema::table('timetables', function (Blueprint $table) {
                $table->dropColumn('schedule');
            });
        }

        Schema::table('timetables', function (Blueprint $table) {
            if (! Schema::hasColumn('timetables', 'grade')) {
                $table->string('grade', 50)->nullable()->after('branch_id');
            }
            if (! Schema::hasColumn('timetables', 'subject_id')) {
                $table->foreignId('subject_id')->nullable()->after('section')->constrained('subjects')->nullOnDelete();
            }
            if (! Schema::hasColumn('timetables', 'teacher_id')) {
                $table->foreignId('teacher_id')->nullable()->after('subject_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('timetables', 'day')) {
                $table->string('day', 20)->nullable()->after('teacher_id');
            }
            if (! Schema::hasColumn('timetables', 'day_order')) {
                $table->unsignedTinyInteger('day_order')->default(1)->after('day');
            }
            if (! Schema::hasColumn('timetables', 'period_number')) {
                $table->unsignedTinyInteger('period_number')->default(1)->after('day_order');
            }
            if (! Schema::hasColumn('timetables', 'start_time')) {
                $table->time('start_time')->nullable()->after('period_number');
            }
            if (! Schema::hasColumn('timetables', 'end_time')) {
                $table->time('end_time')->nullable()->after('start_time');
            }
            if (! Schema::hasColumn('timetables', 'room_number')) {
                $table->string('room_number', 50)->nullable()->after('end_time');
            }
            if (! Schema::hasColumn('timetables', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('timetables', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('timetables', 'grade_level')) {
            DB::table('timetables')
                ->whereNull('grade')
                ->update(['grade' => DB::raw('grade_level')]);
        }

        Schema::table('timetables', function (Blueprint $table) {
            if (! $this->indexExists('timetables', 'timetables_class_period_idx')) {
                $table->index(
                    ['branch_id', 'grade', 'section', 'academic_year_id', 'day', 'period_number'],
                    'timetables_class_period_idx'
                );
            }
            if (! $this->indexExists('timetables', 'timetables_teacher_slot_idx')) {
                $table->index(
                    ['teacher_id', 'day', 'period_number', 'academic_year_id'],
                    'timetables_teacher_slot_idx'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('timetables', function (Blueprint $table) {
            if ($this->indexExists('timetables', 'timetables_class_period_idx')) {
                $table->dropIndex('timetables_class_period_idx');
            }
            if ($this->indexExists('timetables', 'timetables_teacher_slot_idx')) {
                $table->dropIndex('timetables_teacher_slot_idx');
            }
        });
    }

    private function createPeriodTable(): void
    {
        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->string('grade', 50);
            $table->string('grade_level', 50)->nullable();
            $table->string('section', 10);
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('day', 20);
            $table->unsignedTinyInteger('day_order')->default(1);
            $table->unsignedTinyInteger('period_number')->default(1);
            $table->time('start_time');
            $table->time('end_time');
            $table->string('room_number', 50)->nullable();
            $table->string('academic_year', 20)->nullable();
            $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'grade', 'section', 'academic_year_id', 'day', 'period_number'], 'timetables_class_period_idx');
            $table->index(['teacher_id', 'day', 'period_number', 'academic_year_id'], 'timetables_teacher_slot_idx');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $db = $connection->getDatabaseName();

        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$db, $table, $index]
        );

        return count($rows) > 0;
    }
};