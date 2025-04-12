<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Staff;
use App\Models\Role;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
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
                ->where('role', '!=', 'staff') // Filter non-staff users
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
                'errors' => $e->errors()
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
     * Add a user to staff with specific role.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\Role  $role
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, User $user)
    {
        try {
            $validateData = $request->validate([
                'role_id' => 'required|exists:roles,id',
                'branch_id' => 'required|exists:branches,id',
                'business_id' => 'required|exists:businesses,id',
            ]);
            if ($user->role === 'staff') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User is already staff'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            $user->update(['role' => 'staff','branch_id' => $validateData['branch_id'],'business_id' => $validateData['business_id']]);

            $staff = $user->staff()->create([
                'role_id' => $validateData['role_id'],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'User added to staff successfully',
                'data' => $staff
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
            $user->staff()->delete();
            $user->update(['role' => 'guest']);

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
    public function branch_manager(User $user)
    {
        try {
            if($user->role !== 'branch_manager' && Auth::user()->business_id == $user->business_id){
              $user->update(['role' => 'branch_manager']);
              return response()->json([
                'status' => 'success',
                'message' => 'staff is now a branch manager'
              ], Response::HTTP_CREATED);
            }
            
            if($user->role === 'branch_manager') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User is already a branch manager'
                ], Response::HTTP_BAD_REQUEST);
            }
            
        }
        catch(\Exception $e){
            Log::error('Branch manager failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to switch role'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function branch_manager_list(Request $request)
    {
        try {
            $branch_id = Auth::user()->branch_id;
            $branch_managers = User::where('role', 'branch_manager')
                ->where('branch_id', $branch_id)
                ->get(['id', 'name', 'email']);
            
            return response()->json([
                'status' => 'success',
                'data' => $branch_managers
            ], Response::HTTP_OK);
        }
        catch(\Exception $e){
            Log::error('Branch manager list failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get branch managers'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function branch_manager_destroy(User $user)
    {
        try {
            $user->update(['role' => 'staff']);
            return response()->json([
                'status' => 'success',
                'message' => 'Branch manager removed successfully'
            ], Response::HTTP_OK);
        }
        catch(\Exception $e){
            Log::error('Branch manager destroy failed: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove branch manager'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}