<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    /**
     * Display a listing of the staff.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 5); // Default 5 items per page
            $business_id = Auth::user()->business_id;
            $staff = User::where('role', 'staff')
                ->where('business_id', $business_id)
                ->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $staff->items(),
                'pagination' => [
                    'current_page' => $staff->currentPage(),
                    'last_page' => $staff->lastPage(),
                    'per_page' => $staff->perPage(),
                    'total' => $staff->total(),
                    'from' => $staff->firstItem(),
                    'to' => $staff->lastItem(),
                ]
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Staff listing failed: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve staff'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * Display the specified staff member.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(User $user)
    {
        try {
            $staff = User::where('id', $user->id)
                ->where('role', 'staff')
                ->first();

            if (!$staff) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Staff not found'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'status' => 'success',
                'data' => $staff
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Staff retrieval failed: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve staff'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * Search for users who can be added as staff.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        try {
            $request->validate(['name' => 'required|string']);

            $users = User::where('name', 'like', "%{$request->name}%")
                ->where('role','customer') // Filter only customers
                ->limit(10)
                ->get(['id', 'name', 'email']);

            return response()->json([
                'status' => 'success',
                'data' => $users
            ], Response::HTTP_OK);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->getMessage() //get the summary of the validation errors
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (\Exception $e) {
            Log::error('Staff search failed: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to search for users'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Add a user to staff.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(User $user) //here been use the route model binding
    {
        try {
            if ($user->role === 'staff') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User is already staff'
                ], Response::HTTP_BAD_REQUEST);
            }

            $business_id = Auth::user()->business_id;
            $user->update(['role' => 'staff','business_id' => $business_id]);

            return response()->json([
                'status' => 'success',
                'message' => 'User added to staff successfully'
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Adding staff failed: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add user to staff'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(User $user)
    {
        try {
            $user->update(['role' => 'customer','business_id' => null]);

            return response()->json([
                'status' => 'success',
                'message' => 'User removed from staff successfully'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Removing staff failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove user from staff'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}