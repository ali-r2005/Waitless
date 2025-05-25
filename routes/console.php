<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Queue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Command to reset all active queues at midnight
Artisan::command('queues:reset-active', function () {
    // Get all active queues
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
})->purpose('Reset all active queues to inactive status')
  ->schedule(fn (Schedule $schedule) => $schedule->dailyAt('00:00'));

// Add more scheduled tasks here as needed