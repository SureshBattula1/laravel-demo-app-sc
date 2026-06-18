<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetupPermissions extends Command
{
    protected $signature = 'permissions:setup';
    protected $description = 'Setup permission system tables';

    public function handle()
    {
        $this->info('🔐 Setting up permission system tables...');
        $this->info('');

        try {
            // Drop old tables if they exist (in correct order due to foreign keys)
            $this->info('Dropping old tables if they exist...');
            Schema::dropIfExists('user_permissions');
            Schema::dropIfExists('user_roles');
            Schema::dropIfExists('role_permissions');
            Schema::dropIfExists('permissions');
            Schema::dropIfExists('modules');
            Schema::dropIfExists('roles');

            $this->info('✅ Old tables dropped');
            $this->info('');

            // Now run the migration
            $this->info('Running migration...');
            $this->call('migrate', ['--force' => true]);

            // Mark as successful
            $this->info('');
            $this->info('✅ Permission tables created successfully!');
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to setup tables: ' . $e->getMessage());
            return 1;
        }
    }
}

