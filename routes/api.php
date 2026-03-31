<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BusinessManagement\StaffController;
use App\Http\Controllers\QueueManagement\QueueController;
use App\Http\Controllers\QueueManagement\QueueManager; 
use App\Http\Controllers\BusinessManagement\BusinessController;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\QueueManagement\CustomerController;

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
    Route::prefix('queues')->group(function () {
        // Customer queue operations
        Route::get('/{queue}/users', [QueueManager::class, 'getQueueCustomers']);
        Route::post('/{queue}/users/{user}', [QueueManager::class, 'addCustomerToQueue']);
        Route::delete('/queue-users/{queueUser}', [QueueManager::class, 'removeCustomerFromQueue']);
        Route::put('/queue-users/{queueUser}/mark-late', [QueueManager::class, 'markCustomerAsLate']);
        Route::put('/queue-users/{queueUser}/reinsert', [QueueManager::class, 'reinsertCustomer']);
        Route::put('/queue-users/{queueUser}/move', [QueueManager::class, 'moveCustomer']);
        Route::put('/queue-users/{queueUser}/cancel', [QueueManager::class, 'cancelCustomer']);
        Route::put('/{queue}/activate', [QueueManager::class,'activateQueue']);
        Route::put('/{queue}/deactivate', [QueueManager::class,'deactivateQueue']);
        Route::put('/{queue}/pause', [QueueManager::class,'pauseQueue']);
        Route::put('/{queue}/resume', [QueueManager::class,'resumeQueue']);
        Route::put('/{queue}/call-next', [QueueManager::class,'callNextCustomer']);
        Route::put('/{queue}/complete-serving', [QueueManager::class,'completeServing']);
       
    });
});

// here it should be added that the user with role customer can remove themselves from the queue with the convetion that the user id is the id of the user that is logged in
Route::middleware(['auth:api', 'role:customer'])->prefix('customer')->group(function () {
    Route::delete('/queue-users/{queueUser}', [QueueManager::class, 'removeCustomerFromQueue']);
    Route::get('/queue-users/{queueUser}', [CustomerController::class, 'getQueueCustomer']);
    //route for the customer to get the queues that his in
    Route::get('/queues', [CustomerController::class, 'getQueues']);
    Route::put('/queue-users/{queueUser}/cancel', [QueueManager::class, 'cancelCustomer']);
});

require __DIR__.'/auth.php';