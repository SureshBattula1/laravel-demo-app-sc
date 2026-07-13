<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transport Phase 1: dedicated drivers + route_stops tables, and align the existing
 * transport tables with the multi-tenant conventions (school_id, per-branch unique numbers,
 * driver/stop FKs). Additive and driver-agnostic (works on MySQL and sqlite).
 */
return new class extends Migration
{
    public function up(): void
    {
        // New: drivers (not login users)
        if (!Schema::hasTable('transport_drivers')) {
            Schema::create('transport_drivers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
                $table->string('name');
                $table->string('phone', 20)->nullable();
                $table->string('license_number', 60)->nullable();
                $table->date('license_expiry')->nullable();
                $table->string('address')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['branch_id', 'is_active']);
                $table->unique(['branch_id', 'license_number']);
            });
        }

        // New: ordered route stops (source of truth; future GPS-ready)
        if (!Schema::hasTable('route_stops')) {
            Schema::create('route_stops', function (Blueprint $table) {
                $table->id();
                $table->foreignId('route_id')->constrained('transport_routes')->onDelete('cascade');
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
                $table->integer('sequence_no')->default(1);
                $table->string('stop_name');
                $table->time('pickup_time')->nullable();
                $table->time('drop_time')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->integer('geofence_radius')->nullable(); // metres (Phase 2)
                $table->timestamps();

                $table->index(['route_id', 'sequence_no']);
            });
        }

        // Alter transport_routes: school_id + per-branch unique route_number
        Schema::table('transport_routes', function (Blueprint $table) {
            if (!Schema::hasColumn('transport_routes', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('branch_id')->constrained('schools')->nullOnDelete();
            }
        });
        Schema::table('transport_routes', function (Blueprint $table) {
            $table->dropUnique('transport_routes_route_number_unique');
        });
        Schema::table('transport_routes', function (Blueprint $table) {
            $table->unique(['branch_id', 'route_number']);
        });

        // Alter vehicles: school_id + transport_driver_id + per-branch unique vehicle_number
        Schema::table('vehicles', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicles', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('branch_id')->constrained('schools')->nullOnDelete();
            }
            if (!Schema::hasColumn('vehicles', 'transport_driver_id')) {
                $table->foreignId('transport_driver_id')->nullable()->after('driver_id')
                    ->constrained('transport_drivers')->nullOnDelete();
            }
        });
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique('vehicles_vehicle_number_unique');
        });
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unique(['branch_id', 'vehicle_number']);
        });

        // Alter student_transport: school_id + pickup/drop stop FKs
        Schema::table('student_transport', function (Blueprint $table) {
            if (!Schema::hasColumn('student_transport', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('branch_id')->constrained('schools')->nullOnDelete();
            }
            if (!Schema::hasColumn('student_transport', 'pickup_stop_id')) {
                $table->foreignId('pickup_stop_id')->nullable()->after('stop_name')->constrained('route_stops')->nullOnDelete();
            }
            if (!Schema::hasColumn('student_transport', 'drop_stop_id')) {
                $table->foreignId('drop_stop_id')->nullable()->after('pickup_stop_id')->constrained('route_stops')->nullOnDelete();
            }
        });

        // Backfill school_id from the branch (portable across MySQL/sqlite).
        $branchSchool = DB::table('branches')->pluck('school_id', 'id');
        foreach (['transport_routes', 'vehicles', 'student_transport'] as $table) {
            DB::table($table)->whereNull('school_id')->whereNotNull('branch_id')
                ->select('id', 'branch_id')->orderBy('id')->chunk(500, function ($rows) use ($table, $branchSchool) {
                    foreach ($rows as $row) {
                        if (isset($branchSchool[$row->branch_id])) {
                            DB::table($table)->where('id', $row->id)->update(['school_id' => $branchSchool[$row->branch_id]]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('student_transport', function (Blueprint $table) {
            foreach (['drop_stop_id', 'pickup_stop_id', 'school_id'] as $col) {
                if (Schema::hasColumn('student_transport', $col)) {
                    $table->dropConstrainedForeignId($col);
                }
            }
        });
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'vehicle_number']);
            $table->unique('vehicle_number');
            if (Schema::hasColumn('vehicles', 'transport_driver_id')) {
                $table->dropConstrainedForeignId('transport_driver_id');
            }
            if (Schema::hasColumn('vehicles', 'school_id')) {
                $table->dropConstrainedForeignId('school_id');
            }
        });
        Schema::table('transport_routes', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'route_number']);
            $table->unique('route_number');
            if (Schema::hasColumn('transport_routes', 'school_id')) {
                $table->dropConstrainedForeignId('school_id');
            }
        });
        Schema::dropIfExists('route_stops');
        Schema::dropIfExists('transport_drivers');
    }
};
