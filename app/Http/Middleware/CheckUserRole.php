<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Check if user is authenticated
        if (!$request->user()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. User not authenticated.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // If no roles are required, continue
        if (empty($roles)) {
            return $next($request);
        }

        // Check if the user has any of the required roles
        if (in_array($request->user()->role, $roles)) {
            return $next($request);
        }

        // User doesn't have the required role
        return response()->json([
            'status' => 'error',
            'message' => 'Forbidden. You do not have the required role to access this resource.'
        ], Response::HTTP_FORBIDDEN);
    }
}
