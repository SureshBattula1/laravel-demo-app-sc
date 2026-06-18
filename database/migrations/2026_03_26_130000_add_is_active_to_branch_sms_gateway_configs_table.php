<?php

use App\Models\BranchSmsGatewayConfig;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branch_sms_gateway_configs', function (Blueprint $table) {
            $table->boolean('is_active')->default(false)->after('provider');
        });

        BranchSmsGatewayConfig::query()->chunkById(50, function ($rows) {
            foreach ($rows as $row) {
                $c = $row->credentials;
                $active = ($c['status'] ?? 'Inactive') === 'Active';
                $row->is_active = $active;
                $row->saveQuietly();
            }
        });
    }

    public function down(): void
    {
        Schema::table('branch_sms_gateway_configs', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
