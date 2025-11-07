<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 5); // Default 5 items per page
            $buisinessId = Auth::user()->business_id;
            $roles = Role::where('business_id', $buisinessId)->paginate($perPage);
            if ($roles->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No roles found'
                ], Response::HTTP_NOT_FOUND);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $roles->items(),
                'pagination' => [
                    'current_page' => $roles->currentPage(),
                    'last_page' => $roles->lastPage(),
                    'per_page' => $roles->perPage(),
                    'total' => $roles->total(),
                    'from' => $roles->firstItem(),
                    'to' => $roles->lastItem(),
                ]
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch roles: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve roles'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created role.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:255',
            ]);
            $data['business_id'] = Auth::user()->business_id;
            $role = Role::create($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Role created successfully',
                'data' => $role
            ], Response::HTTP_CREATED);
            
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
            
        } catch (\Exception $e) {
            Log::error('Role creation failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create role'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified role.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, Role $role)
    {
        try {            
            return response()->json([
                'status' => 'success',
                'data' => $role
            ], Response::HTTP_OK);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role not found'
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch role: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve role'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified role.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Role $role)
    {
        try {
            $data = $request->validate([
                'name' => 'string|max:255',
            ]);
            $data['business_id'] = Auth::user()->business_id;

            $role->update($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Role updated successfully',
                'data' => $role
            ], Response::HTTP_OK);
            
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role not found'
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\Exception $e) {
            Log::error('Role update failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update role'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified role.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, Role $role)
    {
        try {
            $role->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Role deleted successfully'
            ], Response::HTTP_OK);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role not found'
            ], Response::HTTP_NOT_FOUND);
            
        } catch (\Exception $e) {
            Log::error('Role deletion failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete role'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}