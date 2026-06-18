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
        Schema::table('class_upgrades', function (Blueprint $table) {
            $table->string('fee_carry_forward_status')->nullable()->after('approved_by');
            $table->decimal('fee_carry_forward_amount', 10, 2)->nullable()->after('fee_carry_forward_status');
            $table->text('notes')->nullable()->after('fee_carry_forward_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_upgrades', function (Blueprint $table) {
            $table->dropColumn(['fee_carry_forward_status', 'fee_carry_forward_amount', 'notes']);
        });
    }
};
