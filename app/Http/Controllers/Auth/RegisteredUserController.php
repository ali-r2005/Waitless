<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Business;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Exception
     */
    public function store(Request $request)
    {
        try {
            // Common validation rules for all users
            $commonRules = [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255', 'unique:' . User::class],
                'phone' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
            ];
            
            // Check if this is a business owner registration
            $isBusinessOwner = $request->has('role') && $request->role === 'business_owner';
            
            // Add business-specific validation rules if this is a business owner
            if ($isBusinessOwner) {
                $businessRules = [
                    'business_name' => 'required|string|max:50',
                    'industry' => 'required|string|max:100',
                    'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                    'role' => 'required|string|in:business_owner',
                ];
                
                $validatedData = $request->validate(array_merge($commonRules, $businessRules));
            } else {
                $validatedData = $request->validate($commonRules);
            }

            // Use database transaction to ensure data integrity
            return DB::transaction(function () use ($request, $validatedData, $isBusinessOwner) {
                // Create the user
                $user = User::create([
                    'name' => $validatedData['name'],
                    'email' => $validatedData['email'],
                    'password' => Hash::make($validatedData['password']),
                    'phone' => $validatedData['phone'],
                ]);

                // Handle business creation for business owners
                if ($isBusinessOwner) {
                    $user->role = $validatedData['role'];
                    // Set default logo path
                    $logoPath = 'images/default_logo.png';
                    
                    // Handle logo upload if provided
                    if ($request->hasFile('logo')) {
                        try {
                            // Store the file in public/images directory
                            $path = $request->file('logo')->store('images', 'public');
                            $logoPath = $path;
                        } catch (\Exception $e) {
                            Log::error('Failed to upload logo: ' . $e->getMessage());
                            // Continue with default logo instead of throwing exception
                            Log::info('Using default logo instead.');
                        }
                    }
                    
                    // Create business record
                    $business = Business::create([
                        'name' => $validatedData['business_name'],
                        'industry' => $validatedData['industry'],
                        'logo' => $logoPath,
                    ]);
                    
                    // Associate business with user
                    $user->business()->associate($business);
                    $user->save();
                    
                    // Create authentication token
                    $token = $user->createToken('auth_token')->plainTextToken;
                    
                    $response = response()->json([
                        'status' => 'success',
                        'message' => 'Business owner registered successfully.',
                        'access_token' => $token,
                        'token_type' => 'Bearer',
                        'user' => $user->fresh(['business']),
                        'business' => $business,
                    ], Response::HTTP_CREATED);
                } else {
                    // Regular user registration
                    $token = $user->createToken('auth_token')->plainTextToken;
                    
                    $response = response()->json([
                        'status' => 'success',
                        'message' => 'User registered successfully.',
                        'access_token' => $token,
                        'token_type' => 'Bearer',
                        'user' => $user,
                    ], Response::HTTP_CREATED);
                }

                // Trigger registration event
                event(new Registered($user));

                return $response;
            });
            
        } catch (ValidationException $e) {
            Log::warning('Validation failed during registration: ' . json_encode($e->errors()));
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (ModelNotFoundException $e) {
            Log::error('Resource not found: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Resource not found',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Registration failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}