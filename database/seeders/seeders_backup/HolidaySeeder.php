<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Holiday;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HolidaySeeder extends Seeder
{
    /**
     * Run the database seeds - Create sample holidays
     */
    public function run(): void
    {
        $this->command->info('Seeding Holiday Management with sample holidays...');

        // Get the first branch and user
        $branch = DB::table('branches')->first();
        $user = DB::table('users')->where('role', 'SuperAdmin')->orWhere('role', 'BranchAdmin')->first();

        if (!$user) {
            $this->command->warn('No user found. Please seed users first.');
            return;
        }

        $currentYear = date('Y');
        $academicYear = $currentYear . '-' . ($currentYear + 1);

        // Clear existing holidays
        Holiday::truncate();

        $holidaysData = [
            // NATIONAL HOLIDAYS
            [
                'title' => 'Republic Day',
                'description' => 'National holiday celebrating the adoption of the Constitution of India',
                'start_date' => ($currentYear + 1) . '-01-26',
                'end_date' => ($currentYear + 1) . '-01-26',
                'type' => 'National',
                'color' => '#FF5733',
                'branch_id' => null,
                'is_recurring' => true
            ],
            [
                'title' => 'Independence Day',
                'description' => 'National holiday celebrating independence from British rule',
                'start_date' => $currentYear . '-08-15',
                'end_date' => $currentYear . '-08-15',
                'type' => 'National',
                'color' => '#FF5733',
                'branch_id' => null,
                'is_recurring' => true
            ],
            [
                'title' => 'Gandhi Jayanti',
                'description' => 'Birthday of Mahatma Gandhi - Father of the Nation',
                'start_date' => $currentYear . '-10-02',
                'end_date' => $currentYear . '-10-02',
                'type' => 'National',
                'color' => '#FF5733',
                'branch_id' => null,
                'is_recurring' => true
            ],
            [
                'title' => 'Christmas',
                'description' => 'Celebration of the birth of Jesus Christ',
                'start_date' => $currentYear . '-12-25',
                'end_date' => $currentYear . '-12-25',
                'type' => 'National',
                'color' => '#FF5733',
                'branch_id' => null,
                'is_recurring' => true
            ],
            
            // SCHOOL HOLIDAYS
            [
                'title' => 'Diwali Break',
                'description' => 'Festival of lights - school closure for celebrations',
                'start_date' => $currentYear . '-10-20',
                'end_date' => $currentYear . '-10-25',
                'type' => 'School',
                'color' => '#3498DB',
                'branch_id' => $branch ? $branch->id : null,
                'is_recurring' => false
            ],
            [
                'title' => 'Winter Vacation',
                'description' => 'Annual winter break',
                'start_date' => $currentYear . '-12-24',
                'end_date' => ($currentYear + 1) . '-01-05',
                'type' => 'School',
                'color' => '#3498DB',
                'branch_id' => $branch ? $branch->id : null,
                'is_recurring' => true
            ],
            [
                'title' => 'Summer Vacation',
                'description' => 'Annual summer break',
                'start_date' => ($currentYear + 1) . '-05-01',
                'end_date' => ($currentYear + 1) . '-06-15',
                'type' => 'School',
                'color' => '#3498DB',
                'branch_id' => $branch ? $branch->id : null,
                'is_recurring' => true
            ],
            
            // OPTIONAL HOLIDAYS
            [
                'title' => 'Guru Nanak Jayanti',
                'description' => 'Birth anniversary of Guru Nanak Dev Ji',
                'start_date' => $currentYear . '-11-15',
                'end_date' => $currentYear . '-11-15',
                'type' => 'Optional',
                'color' => '#9B59B6',
                'branch_id' => null,
                'is_recurring' => true
            ],
            
            // STATE HOLIDAYS
            [
                'title' => 'State Formation Day',
                'description' => 'Celebration of state formation',
                'start_date' => ($currentYear + 1) . '-05-01',
                'end_date' => ($currentYear + 1) . '-05-01',
                'type' => 'State',
                'color' => '#FFA500',
                'branch_id' => null,
                'is_recurring' => true
            ],
            
            // UPCOMING HOLIDAYS
            [
                'title' => 'Annual Sports Day',
                'description' => 'School sports and athletics event',
                'start_date' => Carbon::now()->addDays(15)->format('Y-m-d'),
                'end_date' => Carbon::now()->addDays(15)->format('Y-m-d'),
                'type' => 'School',
                'color' => '#3498DB',
                'branch_id' => $branch ? $branch->id : null,
                'is_recurring' => false
            ],
            [
                'title' => 'Mid-term Break',
                'description' => 'Short break during mid-term examinations',
                'start_date' => Carbon::now()->addDays(30)->format('Y-m-d'),
                'end_date' => Carbon::now()->addDays(32)->format('Y-m-d'),
                'type' => 'School',
                'color' => '#3498DB',
                'branch_id' => $branch ? $branch->id : null,
                'is_recurring' => false
            ]
        ];

        $count = 0;
        foreach ($holidaysData as $data) {
            Holiday::create([
                'branch_id' => $data['branch_id'] ?: ($branch ? $branch->id : 1),
                'name' => $data['title'],  // Old column
                'title' => $data['title'],
                'date' => $data['start_date'],  // Old column
                'description' => $data['description'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'type' => $data['type'],
                'color' => $data['color'],
                'is_recurring' => $data['is_recurring'],
                'academic_year' => $academicYear,
                'is_active' => true,
                'created_by' => $user->id
            ]);
            $count++;
        }

        $this->command->info('✓ Created ' . $count . ' sample holidays');
        
        $nationalCount = count(array_filter($holidaysData, fn($h) => $h['type'] === 'National'));
        $schoolCount = count(array_filter($holidaysData, fn($h) => $h['type'] === 'School'));
        $optionalCount = count(array_filter($holidaysData, fn($h) => $h['type'] === 'Optional'));
        $stateCount = count(array_filter($holidaysData, fn($h) => $h['type'] === 'State'));
        
        $this->command->info('  National Holidays: ' . $nationalCount);
        $this->command->info('  School Holidays: ' . $schoolCount);
        $this->command->info('  Optional Holidays: ' . $optionalCount);
        $this->command->info('  State Holidays: ' . $stateCount);
        $this->command->info('✓ Holiday module seeding complete!');
    }
}
