<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled tasks for fee management
Schedule::command('fees:update-aging')->daily();
Schedule::command('fees:send-overdue-notifications')->daily();
Schedule::command('fees:send-payment-reminders')->weekly();
