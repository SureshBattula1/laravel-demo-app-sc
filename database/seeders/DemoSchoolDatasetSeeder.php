<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Full local demo dataset for Green Valley Demo School (GV-DEMO).
 *
 * Run automatically from DatabaseSeeder when SEED_DEMO_SCHOOL=true (default in Docker/local).
 * Manual: php artisan db:seed --class=DemoSchoolDatasetSeeder
 */
class DemoSchoolDatasetSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('');
        $this->command?->info('🏫 Seeding Green Valley demo school…');

        $this->call([
            DemoOneSchoolSeeder::class,
            DemoSchoolOpsSeeder::class,
            DemoTimetableSeeder::class,
            LibraryDemoDataSeeder::class,
            TransportDemoDataSeeder::class,
        ]);

        $this->command?->info('');
        $this->command?->info('✅ Demo dataset ready.');
        $this->command?->info('   School code: GV-DEMO | Branch: GV-DEMO-MAIN');
        $this->command?->info('   School logins: *@greenvalley.demo / Admin@123');
        $this->command?->info('   Platform admins: superadmin@school.com | admin@school.com / Admin@123');
        $this->command?->info('   Company portal: company.admin@demo.com / Admin@123');
    }
}