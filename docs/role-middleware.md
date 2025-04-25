# Role-Based Access Control Middleware

This document describes the role-based access control system implemented in the Waitless application.

## Overview

The `CheckUserRole` middleware provides route protection based on user roles. It ensures that only authorized users with specific roles can access certain endpoints.

## Implementation

### 1. Middleware Class

The middleware is defined in `app/Http/Middleware/CheckUserRole.php` and checks if the authenticated user has one of the specified roles for a route.

### 2. Registration

The middleware is registered in `bootstrap/app.php` with the alias `role`:

```php
$middleware->alias([
    'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
    'role' => \App\Http\Middleware\CheckUserRole::class,
]);
```

### 3. Usage in Routes

The middleware is applied to routes in `routes/api.php` to enforce role-based access:

```php
// Example: Only business owners and system admins can access these routes
Route::middleware(['auth:sanctum', 'role:business_owner,system_admin'])->group(function () {
    // Routes...
});
```

## Role-Based Access Rules

### Business Owner & System Admin Access

Business owners and system admins have access to:

- All branch management endpoints (`/branches/*`)
- Branch hierarchy management
- Full staff management capabilities

```php
Route::middleware(['auth:sanctum', 'role:business_owner,system_admin'])->group(function () {
    Route::apiResource('branches', BranchController::class);
    Route::get('/branches/{branch}/hierarchy', [BranchController::class, 'hierarchy']);
    Route::post('/branches/{branch}/move-sub-branches', [BranchController::class, 'moveSubBranches']);
    
    Route::get('/users/search', [StaffController::class, 'search']);
    Route::post('/users/{user}/add-to-staff', [StaffController::class, 'store']);
    Route::post('/users/{user}/branch-manager', [StaffController::class, 'branch_manager']);
    Route::get('/branch-managers', [StaffController::class, 'branch_manager_list']);
    Route::delete('/branch-managers/{user}', [StaffController::class, 'branch_manager_destroy']);
});
```

### Branch Manager Access

Branch managers have limited access to:

- User search functionality
- Adding users to staff

```php
Route::middleware(['auth:sanctum', 'role:branch_manager,system_admin'])->group(function () {
    Route::get('/branch-manager/users/search', [StaffController::class, 'search']);
    Route::post('/branch-manager/users/{user}/add-to-staff', [StaffController::class, 'store']);
});
```

## Extending Role-Based Access

To add new roles or modify access rules:

1. Update the middleware routes in `routes/api.php`
2. Use the `role` middleware with appropriate role names: `'role:role1,role2'`

## Error Responses

When access is denied, the middleware returns:

- **401 Unauthorized**: If user is not authenticated
- **403 Forbidden**: If user doesn't have the required role 