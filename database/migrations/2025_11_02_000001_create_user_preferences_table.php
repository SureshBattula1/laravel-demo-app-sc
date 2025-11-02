<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This table stores user-specific preferences and settings
     * Benefits: Scalable, easy to add new preferences, keeps users table clean
     */
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // UI/Theme Preferences
            $table->string('theme', 50)->default('ocean-blue');
            $table->boolean('dark_mode')->default(false);
            $table->string('language', 10)->default('en');
            
            // Notification Preferences
            $table->boolean('email_notifications')->default(true);
            $table->boolean('push_notifications')->default(true);
            $table->boolean('sms_notifications')->default(false);
            
            // Display Preferences
            $table->string('date_format', 20)->default('YYYY-MM-DD');
            $table->string('time_format', 20)->default('24h');
            $table->string('timezone', 50)->default('UTC');
            $table->integer('items_per_page')->default(10);
            
            // Dashboard Preferences
            $table->json('dashboard_widgets')->nullable(); // Store active widgets and their positions
            $table->string('default_view', 50)->default('grid'); // grid, list, compact
            
            // Accessibility Preferences
            $table->boolean('high_contrast')->default(false);
            $table->string('font_size', 20)->default('medium'); // small, medium, large
            $table->boolean('reduce_motion')->default(false);
            
            // Additional Settings (JSON for flexibility)
            $table->json('additional_settings')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->unique('user_id');
            $table->index('theme');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};


