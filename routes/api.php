<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BusinessManagement\StaffController;
use App\Http\Controllers\QueueManagement\QueueController;
use App\Http\Controllers\QueueManagement\QueueManager; 
use App\Http\Controllers\BusinessManagement\BusinessController;
use Illuminate\Support\Facades\Broadcast;

// Register the broadcasting routes with JWT authentication
Broadcast::routes(['middleware' => ['auth:api']]);

Route::middleware(['auth:api'])->get('/user', function (Request $request) {
    return $request->user();
});

// Routes accessible to branch managers
Route::middleware(['auth:api', 'role:business_owner'])->group(function () {
    // Limited staff management for branch managers
    Route::post('/staff/{user}', [StaffController::class, 'store']);
    Route::delete('/staff/{user}', [StaffController::class, 'destroy']);
    Route::get('/staff', [StaffController::class, 'index']);
    Route::get('/staff/{user}', [StaffController::class, 'show']);
});

// Queue routes - accessible based on role
Route::middleware(['auth:api', 'role:staff,business_owner'])->group(function () {
    Route::get('business', [BusinessController::class, 'index']);
    Route::get('/users/search', [StaffController::class, 'search']);

    Route::apiResource('queues', QueueController::class);
    // Queue Management routes
    Route::prefix('queue-management')->group(function () {
        // Customer queue operations
        Route::post('/add-customer', [QueueManager::class, 'addCustomerToQueue']);
        Route::get('/customers', [QueueManager::class, 'getQueueCustomers']);
        
        // Queue operations
        Route::post('/activate', [QueueManager::class, 'activateQueue']);
        Route::post('/call-next', [QueueManager::class, 'callNextCustomer']);
        Route::post('/complete-serving', [QueueManager::class, 'completeServing']);
        
        // Queue pause/resume operations
        Route::post('/pause', [QueueManager::class, 'pauseQueue']);
        Route::post('/resume', [QueueManager::class, 'resumeQueue']);
        
        // Customer position management
        Route::patch('/customers/{id}/move', [QueueManager::class, 'move']);
        
        // Late customer management
        Route::post('/customers/late', [QueueManager::class, 'lateCustomer']);
        Route::get('/customers/late', [QueueManager::class, 'getLateCustomers']);
        Route::post('/customers/reinsert', [QueueManager::class, 'reinsertCustomer']);
        
        // Served customers management
        Route::get('/customers/served-today', [QueueManager::class, 'getCustomersServedToday']);
    });
});

// Queue Management routes accessible to any authenticated user (e.g., customer removing themselves)
Route::middleware(['auth:api'])->prefix('queue-management')->group(function () {
    Route::delete('/remove-customer', [QueueManager::class, 'removeCustomerFromQueue']);
});

// GET /queues/1/users
// POST /queues/1/users
// DELETE /queues/1/users/7


require __DIR__.'/auth.php';