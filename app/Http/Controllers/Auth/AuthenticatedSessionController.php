<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function store(Request $request)
    {
        try {
            $request->validate([
                'email' => ['required', 'string', 'email'],
                'password' => ['required', 'string'],
            ]);

            $credentials = $request->only('email', 'password');

            if (!$token = auth('api')->attempt($credentials)) {
                return response()->json(['message' => 'Invalid login credentials'], 401);
            }

            $user = auth('api')->user();

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
                'status' => 'Login successful',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Login failed.',
                'error' => $e->getMessage(),
            ], 500); 
        }
    }

    public function destroy()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json(['message' => 'Logout successful']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Logout failed.',
                'error' => $e->getMessage(),
            ], 500); 
        }
    }

    public function refresh()
    {
        try {            
            // 1. Attempt to refresh the token. 
            // This will succeed if JWT_REFRESH_TTL is still valid.
            $token = JWTAuth::refresh(JWTAuth::getToken());

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.ttl') * 60, // Include the new expiry
            ]);

        } catch (TokenExpiredException $e) {
            // 2. CATCH: This is specifically for when the JWT_REFRESH_TTL has passed.
            // It means the session is completely dead.
            return response()->json([
                'message' => 'Your session has fully expired. Please log in again.',
                'error_code' => 'session_expired', // Custom code for frontend logic
            ], 401); // Crucially, return 401 Unauthorized

        } catch (TokenInvalidException $e) {
            return response()->json([
                'message' => 'Token is invalid.',
                'error_code' => 'token_invalid',
            ], 401);

        } catch (\Exception $e) {
            // 3. CATCH: Handle other JWT errors (e.g., token not provided).
            return response()->json([
                'message' => 'Token refresh failed.',
                'error' => $e->getMessage(),
            ], 500); 
        }
    }
}