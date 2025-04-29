<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\QueueController;
use App\Http\Controllers\Api\QueueManager;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Roles
Route::apiResource('roles', RoleController::class);

// Routes accessible to business owners
Route::middleware(['auth:sanctum', 'role:business_owner'])->group(function () {
    // Branch management
    Route::apiResource('branches', BranchController::class);
    Route::get('/branches/{branch}/hierarchy', [BranchController::class, 'hierarchy']);
    Route::post('/branches/{branch}/move-sub-branches', [BranchController::class, 'moveSubBranches']);
    
    // Staff management for business owners
    Route::post('/users/{user}/branch-manager', [StaffController::class, 'branch_manager']);
    Route::get('/branch-managers', [StaffController::class, 'branch_manager_list']);
    Route::delete('/branch-managers/{user}', [StaffController::class, 'branch_manager_destroy']);
});

// Routes accessible to branch managers
Route::middleware(['auth:sanctum', 'role:branch_manager,business_owner'])->group(function () {
    // Limited staff management for branch managers
    Route::get('/users/search', [StaffController::class, 'search']);
    Route::post('/users/{user}/add-to-staff', [StaffController::class, 'store']);
});

// Queue routes - accessible based on role
Route::middleware(['auth:sanctum', 'role:staff,branch_manager,business_owner'])->group(function () {
    Route::apiResource('queues', QueueController::class);
    
    // Queue Management routes
    Route::prefix('queue-management')->group(function () {
        // Search users
        Route::get('/search', [QueueManager::class, 'search']);
        
        // Customer queue operations
        Route::post('/add-customer', [QueueManager::class, 'addCustomerToQueue']);
        Route::delete('/remove-customer', [QueueManager::class, 'removeCustomerFromQueue']);
        Route::get('/customers', [QueueManager::class, 'getQueueCustomers']);
        
        // Queue operations
        Route::post('/activate', [QueueManager::class, 'activateQueue']);
        Route::post('/call-next', [QueueManager::class, 'callNextCustomer']);
        Route::post('/complete-serving', [QueueManager::class, 'completeServing']);
        
        // Customer position management
        Route::patch('/customers/{id}/move', [QueueManager::class, 'move']);
        Route::post('/customers/reinsert', [QueueManager::class, 'reinsertCustomer']);
        
        // Late customer management
        Route::post('/customers/late', [QueueManager::class, 'lateCustomer']);
        Route::get('/customers/late', [QueueManager::class, 'getLateCustomers']);
    });
});

require __DIR__.'/auth.php';