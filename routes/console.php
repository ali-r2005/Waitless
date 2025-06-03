<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Queue;
use Illuminate\Support\Facades\Log;

// Display an inspiring quote
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Define the 'queues:reset-active' command
Artisan::command('queues:reset-active', function () {
    $queues = Queue::where('is_active', true)->get();
    $count = 0;

    foreach ($queues as $queue) {
        $queue->is_active = false;
        $queue->save();

        $this->info("Reset queue: {$queue->name} (ID: {$queue->id})");
        Log::info("Reset active status for queue: {$queue->name} (ID: {$queue->id})");
        $count++;
    }

    $this->info("Reset {$count} active queues.");
})->purpose('Reset all active queues to inactive status');

// Schedule the 'queues:reset-active' command to run daily at midnight
Schedule::command('queues:reset-active')->dailyAt('00:00');