<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enhance branches table
        Schema::table('branches', function (Blueprint $table) {
            if (!Schema::hasColumn('branches', 'parent_branch_id')) {
                $table->unsignedBigInteger('parent_branch_id')->nullable()->after('id');
                $table->foreign('parent_branch_id')->references('id')->on('branches')->onDelete('set null');
            }
            if (!Schema::hasColumn('branches', 'branch_type')) {
                $table->enum('branch_type', ['HeadOffice', 'RegionalOffice', 'School', 'Campus', 'SubBranch'])->default('School')->after('name');
            }
            if (!Schema::hasColumn('branches', 'status')) {
                $table->enum('status', ['Active', 'Inactive', 'UnderConstruction', 'Closed'])->default('Active')->after('is_active');
            }
            if (!Schema::hasColumn('branches', 'total_capacity')) {
                $table->integer('total_capacity')->default(0)->after('status');
                $table->integer('current_enrollment')->default(0)->after('total_capacity');
            }
        });

        // Branch Settings
        if (!Schema::hasTable('branch_settings')) {
            Schema::create('branch_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->string('setting_key');
                $table->text('setting_value')->nullable();
                $table->string('setting_type')->default('string');
                $table->timestamps();
                
                $table->unique(['branch_id', 'setting_key']);
            });
        }

        // Branch Transfers
        if (!Schema::hasTable('branch_transfers')) {
            Schema::create('branch_transfers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('from_branch_id')->constrained('branches')->onDelete('restrict');
                $table->foreignId('to_branch_id')->constrained('branches')->onDelete('restrict');
                $table->enum('transfer_type', ['Student', 'Teacher', 'Staff']);
                $table->date('transfer_date');
                $table->text('reason')->nullable();
                $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Completed'])->default('Pending');
                $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
                
                $table->index(['user_id', 'status']);
            });
        }

        // Branch Analytics
        if (!Schema::hasTable('branch_analytics')) {
            Schema::create('branch_analytics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->date('analytics_date');
                $table->string('metric_type');
                $table->decimal('metric_value', 15, 2);
                $table->json('breakdown')->nullable();
                $table->timestamps();
                
                $table->unique(['branch_id', 'analytics_date', 'metric_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_analytics');
        Schema::dropIfExists('branch_transfers');
        Schema::dropIfExists('branch_settings');
        
        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'parent_branch_id')) {
                $table->dropForeign(['parent_branch_id']);
                $table->dropColumn(['parent_branch_id', 'branch_type', 'status', 'total_capacity', 'current_enrollment']);
            }
        });
    }
};

