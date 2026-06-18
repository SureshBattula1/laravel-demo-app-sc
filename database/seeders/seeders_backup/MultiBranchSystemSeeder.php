<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class MultiBranchSystemSeeder extends Seeder
{
    /**
     * Seed multi-branch hierarchical structure with sample data
     */
    public function run(): void
    {
        $this->command->info('Seeding multi-branch system...');

        // 1. Create Head Office
        $headOffice = Branch::create([
            'name' => 'MySchool International - Head Office',
            'code' => 'MYSCHOOL-HQ',
            'branch_type' => 'HeadOffice',
            'parent_branch_id' => null,
            'address' => '100 Education Boulevard, Suite 500',
            'city' => 'New York',
            'state' => 'New York',
            'country' => 'United States',
            'region' => 'Northeast',
            'pincode' => '10001',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'timezone' => 'America/New_York',
            'phone' => '+1-212-555-0100',
            'email' => 'headoffice@myschool.edu',
            'website' => 'https://www.myschool.edu',
            'emergency_contact' => '+1-212-555-0199',
            'principal_name' => 'Dr. Sarah Johnson',
            'principal_contact' => '+1-212-555-0101',
            'principal_email' => 'sarah.johnson@myschool.edu',
            'established_date' => '2000-01-01',
            'board' => 'International',
            'is_main_branch' => true,
            'is_active' => true,
            'status' => 'Active',
            'total_capacity' => 0,
            'current_enrollment' => 0,
        ]);

        // 2. Create Regional Offices
        $northRegion = Branch::create([
            'name' => 'MySchool - North Region',
            'code' => 'MYSCHOOL-NR',
            'branch_type' => 'RegionalOffice',
            'parent_branch_id' => $headOffice->id,
            'address' => '250 Regional Drive',
            'city' => 'Boston',
            'state' => 'Massachusetts',
            'country' => 'United States',
            'region' => 'Northeast',
            'pincode' => '02101',
            'latitude' => 42.3601,
            'longitude' => -71.0589,
            'timezone' => 'America/New_York',
            'phone' => '+1-617-555-0200',
            'email' => 'northregion@myschool.edu',
            'website' => 'https://north.myschool.edu',
            'principal_name' => 'Mr. James Wilson',
            'principal_email' => 'james.wilson@myschool.edu',
            'established_date' => '2005-06-01',
            'is_active' => true,
            'status' => 'Active',
        ]);

        $southRegion = Branch::create([
            'name' => 'MySchool - South Region',
            'code' => 'MYSCHOOL-SR',
            'branch_type' => 'RegionalOffice',
            'parent_branch_id' => $headOffice->id,
            'address' => '300 Southern Way',
            'city' => 'Atlanta',
            'state' => 'Georgia',
            'country' => 'United States',
            'region' => 'Southeast',
            'pincode' => '30301',
            'latitude' => 33.7490,
            'longitude' => -84.3880,
            'timezone' => 'America/New_York',
            'phone' => '+1-404-555-0300',
            'email' => 'southregion@myschool.edu',
            'website' => 'https://south.myschool.edu',
            'principal_name' => 'Ms. Emily Davis',
            'principal_email' => 'emily.davis@myschool.edu',
            'established_date' => '2007-08-15',
            'is_active' => true,
            'status' => 'Active',
        ]);

        // 3. Create Schools under North Region
        $bostonSchool = Branch::create([
            'name' => 'MySchool Boston Campus',
            'code' => 'MYSCHOOL-BOS',
            'branch_type' => 'School',
            'parent_branch_id' => $northRegion->id,
            'address' => '500 Academic Avenue',
            'city' => 'Boston',
            'state' => 'Massachusetts',
            'country' => 'United States',
            'region' => 'Northeast',
            'pincode' => '02115',
            'latitude' => 42.3398,
            'longitude' => -71.0892,
            'timezone' => 'America/New_York',
            'phone' => '+1-617-555-0500',
            'email' => 'boston@myschool.edu',
            'website' => 'https://boston.myschool.edu',
            'principal_name' => 'Dr. Michael Brown',
            'principal_contact' => '+1-617-555-0501',
            'principal_email' => 'michael.brown@myschool.edu',
            'established_date' => '2008-04-15',
            'board' => 'CBSE',
            'total_capacity' => 1500,
            'current_enrollment' => 1250,
            'grades_offered' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'],
            'facilities' => ['Library', 'Computer Lab', 'Science Lab', 'Sports Ground', 'Auditorium', 'Cafeteria'],
            'academic_year_start' => '08-01',
            'academic_year_end' => '06-30',
            'current_academic_year' => '2024-2025',
            'has_library' => true,
            'has_lab' => true,
            'has_sports' => true,
            'has_transport' => true,
            'has_canteen' => true,
            'has_hostel' => false,
            'is_residential' => false,
            'is_active' => true,
            'status' => 'Active',
        ]);

        $nycSchool = Branch::create([
            'name' => 'MySchool New York Downtown',
            'code' => 'MYSCHOOL-NYC',
            'branch_type' => 'School',
            'parent_branch_id' => $northRegion->id,
            'address' => '750 Manhattan Street',
            'city' => 'New York',
            'state' => 'New York',
            'country' => 'United States',
            'region' => 'Northeast',
            'pincode' => '10002',
            'latitude' => 40.7580,
            'longitude' => -73.9855,
            'timezone' => 'America/New_York',
            'phone' => '+1-212-555-0700',
            'email' => 'newyork@myschool.edu',
            'website' => 'https://nyc.myschool.edu',
            'principal_name' => 'Mrs. Lisa Anderson',
            'principal_contact' => '+1-212-555-0701',
            'principal_email' => 'lisa.anderson@myschool.edu',
            'established_date' => '2010-01-20',
            'board' => 'CBSE',
            'total_capacity' => 2000,
            'current_enrollment' => 1800,
            'grades_offered' => ['Pre-K', 'K', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'],
            'facilities' => ['Library', 'Computer Lab', 'Science Lab', 'Art Studio', 'Music Room', 'Gymnasium', 'Rooftop Garden'],
            'academic_year_start' => '09-01',
            'academic_year_end' => '06-30',
            'current_academic_year' => '2024-2025',
            'has_library' => true,
            'has_lab' => true,
            'has_sports' => true,
            'has_transport' => true,
            'has_canteen' => true,
            'has_hostel' => false,
            'is_residential' => false,
            'is_active' => true,
            'status' => 'Active',
        ]);

        // 4. Create Schools under South Region
        $atlantaSchool = Branch::create([
            'name' => 'MySchool Atlanta Center',
            'code' => 'MYSCHOOL-ATL',
            'branch_type' => 'School',
            'parent_branch_id' => $southRegion->id,
            'address' => '1000 Peachtree Road',
            'city' => 'Atlanta',
            'state' => 'Georgia',
            'country' => 'United States',
            'region' => 'Southeast',
            'pincode' => '30309',
            'latitude' => 33.7756,
            'longitude' => -84.3963,
            'timezone' => 'America/New_York',
            'phone' => '+1-404-555-1000',
            'email' => 'atlanta@myschool.edu',
            'website' => 'https://atlanta.myschool.edu',
            'principal_name' => 'Dr. Robert Taylor',
            'principal_contact' => '+1-404-555-1001',
            'principal_email' => 'robert.taylor@myschool.edu',
            'established_date' => '2012-03-10',
            'board' => 'ICSE',
            'total_capacity' => 1800,
            'current_enrollment' => 1600,
            'grades_offered' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'],
            'facilities' => ['Library', 'Computer Lab', 'Science Lab', 'Swimming Pool', 'Basketball Court', 'Auditorium'],
            'academic_year_start' => '08-15',
            'academic_year_end' => '06-15',
            'current_academic_year' => '2024-2025',
            'has_library' => true,
            'has_lab' => true,
            'has_sports' => true,
            'has_transport' => true,
            'has_canteen' => true,
            'has_hostel' => true,
            'is_residential' => true,
            'is_active' => true,
            'status' => 'Active',
        ]);

        // 5. Create Campus under NYC School
        $nycEastCampus = Branch::create([
            'name' => 'MySchool NYC East Campus',
            'code' => 'MYSCHOOL-NYC-E',
            'branch_type' => 'Campus',
            'parent_branch_id' => $nycSchool->id,
            'address' => '900 East River Drive',
            'city' => 'New York',
            'state' => 'New York',
            'country' => 'United States',
            'region' => 'Northeast',
            'pincode' => '10009',
            'latitude' => 40.7217,
            'longitude' => -73.9753,
            'timezone' => 'America/New_York',
            'phone' => '+1-212-555-0900',
            'email' => 'nyc-east@myschool.edu',
            'principal_name' => 'Mr. David Martinez',
            'principal_email' => 'david.martinez@myschool.edu',
            'established_date' => '2018-09-01',
            'board' => 'CBSE',
            'total_capacity' => 800,
            'current_enrollment' => 650,
            'grades_offered' => ['1', '2', '3', '4', '5', '6'],
            'facilities' => ['Library', 'Computer Lab', 'Play Area'],
            'has_library' => true,
            'has_lab' => true,
            'has_sports' => true,
            'has_transport' => true,
            'is_active' => true,
            'status' => 'Active',
        ]);

        // 6. Create a branch under construction
        $miamiSchool = Branch::create([
            'name' => 'MySchool Miami Campus',
            'code' => 'MYSCHOOL-MIA',
            'branch_type' => 'School',
            'parent_branch_id' => $southRegion->id,
            'address' => '1500 Ocean Drive',
            'city' => 'Miami',
            'state' => 'Florida',
            'country' => 'United States',
            'region' => 'Southeast',
            'pincode' => '33139',
            'latitude' => 25.7617,
            'longitude' => -80.1918,
            'timezone' => 'America/New_York',
            'phone' => '+1-305-555-1500',
            'email' => 'miami@myschool.edu',
            'opening_date' => '2025-08-01',
            'total_capacity' => 1200,
            'current_enrollment' => 0,
            'is_active' => false,
            'status' => 'UnderConstruction',
        ]);

        $this->command->info('Created ' . Branch::count() . ' branches in hierarchy.');

        // 7. Create Branch Settings
        $this->createBranchSettings($bostonSchool, $nycSchool, $atlantaSchool);

        // 8. Create Branch Admins
        $this->createBranchAdmins($northRegion, $southRegion, $bostonSchool, $nycSchool, $atlantaSchool);

        $this->command->info('Multi-branch system seeding completed successfully!');
    }

    private function createBranchSettings($bostonSchool, $nycSchool, $atlantaSchool)
    {
        $this->command->info('Creating branch-specific settings...');

        // Boston School Settings
        $bostonSettings = [
            ['key' => 'late_fee_percentage', 'value' => '5', 'type' => 'number', 'category' => 'financial'],
            ['key' => 'allow_online_admission', 'value' => 'true', 'type' => 'boolean', 'category' => 'operational'],
            ['key' => 'library_fine_per_day', 'value' => '2', 'type' => 'number', 'category' => 'library'],
            ['key' => 'max_absent_percentage', 'value' => '25', 'type' => 'number', 'category' => 'academic'],
            ['key' => 'grading_system', 'value' => json_encode(['A+' => 95, 'A' => 90, 'B+' => 85, 'B' => 80, 'C' => 70]), 'type' => 'json', 'category' => 'academic'],
        ];

        foreach ($bostonSettings as $setting) {
            BranchSetting::create(array_merge(['branch_id' => $bostonSchool->id], $setting));
        }

        // NYC School Settings (different fees)
        $nycSettings = [
            ['key' => 'late_fee_percentage', 'value' => '7', 'type' => 'number', 'category' => 'financial'],
            ['key' => 'allow_online_admission', 'value' => 'true', 'type' => 'boolean', 'category' => 'operational'],
            ['key' => 'library_fine_per_day', 'value' => '3', 'type' => 'number', 'category' => 'library'],
            ['key' => 'max_absent_percentage', 'value' => '20', 'type' => 'number', 'category' => 'academic'],
        ];

        foreach ($nycSettings as $setting) {
            BranchSetting::create(array_merge(['branch_id' => $nycSchool->id], $setting));
        }

        // Atlanta School Settings
        $atlantaSettings = [
            ['key' => 'late_fee_percentage', 'value' => '6', 'type' => 'number', 'category' => 'financial'],
            ['key' => 'hostel_fee_monthly', 'value' => '500', 'type' => 'number', 'category' => 'financial'],
            ['key' => 'allow_online_admission', 'value' => 'true', 'type' => 'boolean', 'category' => 'operational'],
        ];

        foreach ($atlantaSettings as $setting) {
            BranchSetting::create(array_merge(['branch_id' => $atlantaSchool->id], $setting));
        }
    }

    private function createBranchAdmins($northRegion, $southRegion, $bostonSchool, $nycSchool, $atlantaSchool)
    {
        $this->command->info('Creating branch administrators...');

        // North Region Admin
        User::create([
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john.smith@myschool.edu',
            'phone' => '+16175550201',
            'password' => Hash::make('Admin@123'),
            'role' => 'BranchAdmin',
            'branch_id' => $northRegion->id,
            'is_active' => true
        ]);

        // South Region Admin
        User::create([
            'first_name' => 'Mary',
            'last_name' => 'Johnson',
            'email' => 'mary.johnson@myschool.edu',
            'phone' => '+14045550301',
            'password' => Hash::make('Admin@123'),
            'role' => 'BranchAdmin',
            'branch_id' => $southRegion->id,
            'is_active' => true
        ]);

        // Boston School Admin
        User::create([
            'first_name' => 'Tom',
            'last_name' => 'Wilson',
            'email' => 'tom.wilson@myschool.edu',
            'phone' => '+16175550502',
            'password' => Hash::make('Admin@123'),
            'role' => 'BranchAdmin',
            'branch_id' => $bostonSchool->id,
            'is_active' => true
        ]);

        // NYC School Admin
        User::create([
            'first_name' => 'Jennifer',
            'last_name' => 'Davis',
            'email' => 'jennifer.davis@myschool.edu',
            'phone' => '+12125550702',
            'password' => Hash::make('Admin@123'),
            'role' => 'BranchAdmin',
            'branch_id' => $nycSchool->id,
            'is_active' => true
        ]);

        // Atlanta School Admin
        User::create([
            'first_name' => 'Richard',
            'last_name' => 'Brown',
            'email' => 'richard.brown@myschool.edu',
            'phone' => '+14045551002',
            'password' => Hash::make('Admin@123'),
            'role' => 'BranchAdmin',
            'branch_id' => $atlantaSchool->id,
            'is_active' => true
        ]);
    }
}

