<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_templates', function (Blueprint $table) {
            $table->string('module_type', 32)->nullable()->after('audience');
            $table->index(['branch_id', 'module_type', 'is_active']);
        });

        $hints = [
            'attendance' => ['attendance', 'present', 'absent'],
            'holidays' => ['holiday'],
            'exams' => ['exam', 'result'],
            'fees' => ['fee', 'due', 'remind'],
            'assignments' => ['assignment', 'homework'],
            'custom' => ['custom'],
        ];
        foreach ($hints as $moduleType => $needles) {
            foreach ($needles as $needle) {
                DB::table('sms_templates')
                    ->whereNull('module_type')
                    ->whereRaw('LOWER(name) LIKE ?', ['%'.$needle.'%'])
                    ->update(['module_type' => $moduleType]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('sms_templates', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'module_type', 'is_active']);
            $table->dropColumn('module_type');
        });
    }
};
