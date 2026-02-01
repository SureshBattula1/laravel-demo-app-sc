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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_password_changed')->default(false)->after('password');
            $table->timestamp('password_changed_at')->nullable()->after('is_password_changed');
            $table->string('otp_code', 6)->nullable()->after('password_changed_at');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_password_changed', 'password_changed_at', 'otp_code', 'otp_expires_at']);
        });
    }
};
