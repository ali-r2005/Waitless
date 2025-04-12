<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\BranchController;

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});

// Roles
Route::apiResource('roles', RoleController::class);

// Staff
Route::get('/users/search', [StaffController::class, 'search']);
Route::post('/users/{user}/add-to-staff', [StaffController::class, 'store']);
Route::post('/users/{user}/branch-manager', [StaffController::class, 'branch_manager'])->middleware('auth:sanctum');
Route::get('/branch-managers', [StaffController::class, 'branch_manager_list'])->middleware('auth:sanctum');
Route::delete('/branch-managers/{user}', [StaffController::class, 'branch_manager_destroy'])->middleware('auth:sanctum');

// Branches
Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('branches', BranchController::class);
    Route::get('/branches/{branch}/hierarchy', [BranchController::class, 'hierarchy']);
    Route::post('/branches/{branch}/move-sub-branches', [BranchController::class, 'moveSubBranches']);
});

require __DIR__.'/auth.php';