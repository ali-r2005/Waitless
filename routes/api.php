<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\QueueController;

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
});

require __DIR__.'/auth.php';