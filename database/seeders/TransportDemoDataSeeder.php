<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Demo data for Transport Phase 1: per branch, a few drivers, vehicles, routes with
 * ordered stops, and a sample of students assigned to routes/stops.
 *
 * Idempotent demo reset: clears transport tables first, then reseeds. Branch/school scoped.
 */
class TransportDemoDataSeeder extends Seeder
{
    private const DRIVER_NAMES = ['Ramesh Yadav', 'Sunil Patil', 'Vikram Singh', 'Anil Kumar', 'Mahesh Rao'];
    private const ROUTE_NAMES = ['North Route', 'City Center', 'East Zone', 'West Line', 'South Loop'];
    private const STOP_NAMES = ['Main Gate', 'Market Square', 'Railway Station', 'Green Park', 'Lake View', 'Old Town', 'Tech Park', 'Bus Depot'];
    private const VEHICLE_TYPES = ['Bus', 'Van', 'Car'];

    public function run(): void
    {
        $now = Carbon::now();

        // Demo reset (FK-safe order)
        DB::table('student_transport')->delete();
        DB::table('route_stops')->delete();
        DB::table('vehicles')->delete();
        DB::table('transport_routes')->delete();
        DB::table('transport_drivers')->delete();

        $branches = DB::table('branches')->whereNull('deleted_at')->orderBy('id')->get();
        $drivers = $routes = $stops = $vehicles = $assigns = 0;

        foreach ($branches as $branch) {
            $schoolId = $branch->school_id;

            // Drivers
            $driverIds = [];
            for ($d = 1; $d <= 3; $d++) {
                $driverIds[] = DB::table('transport_drivers')->insertGetId([
                    'branch_id' => $branch->id,
                    'school_id' => $schoolId,
                    'name' => self::DRIVER_NAMES[($branch->id + $d) % count(self::DRIVER_NAMES)],
                    'phone' => '9' . str_pad((string) ($branch->id * 100 + $d), 9, '0', STR_PAD_LEFT),
                    'license_number' => 'DL-' . $branch->id . '-' . str_pad((string) $d, 3, '0', STR_PAD_LEFT),
                    'license_expiry' => $now->copy()->addYears(2)->toDateString(),
                    'address' => ($branch->city ?? 'NA') . ', ' . ($branch->state ?? 'NA'),
                    'is_active' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $drivers++;
            }

            // Routes (+ ordered stops)
            $routeStopMap = []; // routeId => [stopId,...]
            $routeIds = [];
            for ($r = 1; $r <= 3; $r++) {
                $routeId = DB::table('transport_routes')->insertGetId([
                    'branch_id' => $branch->id,
                    'school_id' => $schoolId,
                    'route_number' => 'RT-' . $branch->id . '-' . str_pad((string) $r, 3, '0', STR_PAD_LEFT),
                    'route_name' => self::ROUTE_NAMES[($branch->id + $r) % count(self::ROUTE_NAMES)],
                    'description' => 'Demo route',
                    'stops' => json_encode([]),
                    'distance' => rand(8, 25),
                    'estimated_time' => rand(25, 60),
                    'fare' => rand(500, 1500),
                    'is_active' => 1,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $routeIds[] = $routeId;

                $numStops = rand(3, 5);
                $names = [];
                $stopIds = [];
                for ($s = 1; $s <= $numStops; $s++) {
                    $name = self::STOP_NAMES[($r + $s) % count(self::STOP_NAMES)];
                    $names[] = $name;
                    $pick = $now->copy()->setTime(7, 0)->addMinutes(($s - 1) * 10);
                    $drop = $now->copy()->setTime(14, 0)->addMinutes(($s - 1) * 10);
                    $stopIds[] = DB::table('route_stops')->insertGetId([
                        'route_id' => $routeId,
                        'branch_id' => $branch->id,
                        'school_id' => $schoolId,
                        'sequence_no' => $s,
                        'stop_name' => $name,
                        'pickup_time' => $pick->format('H:i:s'),
                        'drop_time' => $drop->format('H:i:s'),
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $stops++;
                }
                DB::table('transport_routes')->where('id', $routeId)->update(['stops' => json_encode($names)]);
                $routeStopMap[$routeId] = $stopIds;
                $routes++;
            }

            // Vehicles (assign a driver + route)
            for ($v = 1; $v <= 3; $v++) {
                DB::table('vehicles')->insert([
                    'branch_id' => $branch->id,
                    'school_id' => $schoolId,
                    'route_id' => $routeIds[($v - 1) % count($routeIds)],
                    'transport_driver_id' => $driverIds[($v - 1) % count($driverIds)],
                    'vehicle_number' => 'VH-' . $branch->id . '-' . str_pad((string) $v, 3, '0', STR_PAD_LEFT),
                    'vehicle_type' => self::VEHICLE_TYPES[($v - 1) % count(self::VEHICLE_TYPES)],
                    'make' => 'Tata',
                    'model' => 'Starbus',
                    'capacity' => rand(20, 45),
                    'insurance_expiry' => $now->copy()->addYear()->toDateString(),
                    'fitness_expiry' => $now->copy()->addYears(2)->toDateString(),
                    'status' => 'Active',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $vehicles++;
            }

            // Assign a sample of students to the first route
            $studentUsers = DB::table('users')->where('role', 'Student')->where('branch_id', $branch->id)
                ->orderBy('id')->limit(8)->pluck('id')->all();
            if (!empty($routeIds) && !empty($studentUsers)) {
                $routeId = $routeIds[0];
                $stopIds = $routeStopMap[$routeId];
                $firstStop = DB::table('route_stops')->where('id', $stopIds[0])->first();
                foreach ($studentUsers as $uid) {
                    DB::table('student_transport')->insert([
                        'student_id' => $uid,
                        'route_id' => $routeId,
                        'vehicle_id' => null,
                        'branch_id' => $branch->id,
                        'school_id' => $schoolId,
                        'stop_name' => $firstStop->stop_name,
                        'pickup_stop_id' => $stopIds[0],
                        'drop_stop_id' => $stopIds[count($stopIds) - 1],
                        'pickup_time' => $firstStop->pickup_time,
                        'drop_time' => $firstStop->drop_time,
                        'monthly_fee' => rand(500, 1200),
                        'status' => 'Active',
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $assigns++;
                }
            }
        }

        $this->command->info("🚌 Transport demo: {$drivers} drivers, {$vehicles} vehicles, {$routes} routes, {$stops} stops, {$assigns} student assignments across {$branches->count()} branches.");
    }
}
