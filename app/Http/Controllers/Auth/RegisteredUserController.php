<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $commonRules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|in:student,system_admin,business_admin,staff,customer',
        ];
        $roleRules = [
            'business_admin' => [
                'business_name' => 'required|string|max:50',
                'industry' => 'required|string',
                'logo' => 'required|string',
            ],
        ];
        $validatedData = $request->validate(
            array_merge($commonRules, $roleRules[$request->role] ?? [])
        );

        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'role' => $validatedData['role'],
        ]);

        $response = $this->handleRoleSpecificData($user, $validatedData);

        event(new Registered($user));

        return $response ?? response()->json([
            'message' => 'User registered successfully.',
            'user' => $user,
        ]);
    }

    /**
     * Handle role-specific data for the user.
     */
    protected function handleRoleSpecificData(User $user, array $data)
    {
        switch ($data['role']) {
            case 'business_admin':
                $logoPath = $data['logo']; // Store the logo
                $business = \App\Models\Business::create([
                    'name' => $data['business_name'],
                    'industry' => $data['industry'],
                    'logo' => $logoPath,
                ]);
                $user->business()->associate($business);
                $user->save();
                $token = $user->createToken('auth_token')->plainTextToken;
                return response()->json([
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'user' => $user,
                ]);
                break;
            case 'staff':
                $staff = $user->staff()->create([
                    'phone_number' => $data['phone_number'] ?? null,
                    'user_id' => $user->id,
                    'business_id' => $data['business_id'],
                    'role_id' => $data['role_id']
                ]);
                break;
        }
    }
}