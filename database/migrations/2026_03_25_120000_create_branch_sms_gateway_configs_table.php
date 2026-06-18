<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Branch-scoped SMS gateway credentials (secrets encrypted at rest via Eloquent cast).
     */
    public function up(): void
    {
        Schema::create('branch_sms_gateway_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('provider', 32); // twilio, msg91, local_text, nexmo
            $table->text('credentials'); // encrypted JSON
            $table->timestamps();

            $table->unique(['branch_id', 'provider']);
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_sms_gateway_configs');
    }
};
