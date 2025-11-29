<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->integer('duration')->nullable()->after('end_time')->comment('Duration in minutes');
            $table->string('room_number')->nullable()->after('duration');
            $table->foreignId('invigilator_id')->nullable()->after('room_number')->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dropForeign(['invigilator_id']);
            $table->dropColumn(['duration', 'room_number', 'invigilator_id']);
        });
    }
};
