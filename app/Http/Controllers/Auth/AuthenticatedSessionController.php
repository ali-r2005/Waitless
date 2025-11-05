<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticatedSessionController extends Controller
{
    public function store(Request $request)
    {
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
    }

    public function destroy(Request $request)
    {
        auth('api')->logout();

        return response()->json(['message' => 'Logout successful']);
    }

    public function refresh(Request $request)
    {
        try {            
            // 1. Attempt to refresh the token. 
            // This will succeed if JWT_REFRESH_TTL is still valid.
            $token = auth('api')->refresh();

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60, // Include the new expiry
            ]);

        } catch (TokenExpiredException $e) {
            // 2. CATCH: This is specifically for when the JWT_REFRESH_TTL has passed.
            // It means the session is completely dead.
            return response()->json([
                'message' => 'Your session has fully expired. Please log in again.',
                'error_code' => 'session_expired', // Custom code for frontend logic
            ], 401); // Crucially, return 401 Unauthorized

        } catch (\Exception $e) {
            // 3. CATCH: Handle other generic errors (e.g., token not provided, invalid signature).
            return response()->json([
                'message' => 'Token refresh failed.',
                'error' => $e->getMessage(),
            ], 401); 
        }
    }
}