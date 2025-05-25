<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Queue;
use App\Models\Branch;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Models\LatecomerQueue;

class QueueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = Queue::query();
            
            // Apply role-based filters
            switch ($user->role) {
                case 'staff':
                    // Staff can only see queues they created
                    $staffId = Staff::where('user_id', $user->id)->first()?->id;
                    if (!$staffId) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Staff record not found'
                        ], Response::HTTP_NOT_FOUND);
                    }
                    $query->where('staff_id', $staffId);
                    break;
                    
                case 'branch_manager':
                    // Branch managers can see all queues in their branch
                    if (!$user->branch_id) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Branch not assigned to user'
                        ], Response::HTTP_BAD_REQUEST);
                    }
                    $query->where('branch_id', $user->branch_id);
                    break;
                    
                case 'business_owner':
                    // Business owners can see all queues in their business
                    if (!$user->business_id) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Business not assigned to user'
                        ], Response::HTTP_BAD_REQUEST);
                    }
                    
                    // Get all branches for this business
                    $branchIds = Branch::where('business_id', $user->business_id)
                        ->pluck('id')
                        ->toArray();
                        
                    $query->whereIn('branch_id', $branchIds);
                    break;
                    
                default:
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized role'
                    ], Response::HTTP_FORBIDDEN);
            }
            
            // Add any filters from request
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }
            
            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }
            
            $queues = $query->with(['branch', 'staff','users'])->get();
            $latecomerQueues = LatecomerQueue::all();
            return response()->json([
                'status' => 'success',
                'data' => $queues,
                'latecomer_queues' => $latecomerQueues
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            Log::error('Queue listing failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve queues: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'scheduled_date' => 'nullable|date',
                'is_active' => 'boolean|nullable',
                'start_time' => 'nullable|date_format:H:i',
                'preferences' => 'nullable|json'
            ]);
            
            // Check if user has permission to create queue for this branch
            switch ($user->role) {
                case 'staff':
                    // Get staff ID
                    $staff = Staff::where('user_id', $user->id)->first();
                    if (!$staff) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Staff record not found'
                        ], Response::HTTP_NOT_FOUND);
                    }
                    $validatedData['staff_id'] = $staff->id;
                    $validatedData['branch_id'] = $user->branch_id;
                    break;
                    
                case 'branch_manager':
                        $staff = Staff::where('user_id', $user->id)->first();
                        if ($staff) {
                            $validatedData['staff_id'] = $staff->id;
                            $validatedData['branch_id'] = $user->branch_id;
                        }
                    break;
                    
                default:
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized role'
                    ], Response::HTTP_FORBIDDEN);
            }
            
            $queue = Queue::create($validatedData);
            $latecomerQueue = LatecomerQueue::create(['queue_id' => $queue->id]);
            return response()->json([
                'status' => 'success',
                'message' => 'Queue created successfully',
                'data' => $queue
            ], Response::HTTP_CREATED);
            
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
            
        } catch (\Exception $e) {
            Log::error('Queue creation failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create queue: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $queue = Queue::with(['branch', 'staff', 'users'])->find($id);
            
            if (!$queue) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Queue not found'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $user = Auth::user();
            
            // Check if user has permission to view this queue
            switch ($user->role) {
                case 'staff':
                    // Staff can only view their own queues
                    $staffId = Staff::where('user_id', $user->id)->first()?->id;
                    if (!$staffId || $queue->staff_id != $staffId) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'You can only view your own queues'
                        ], Response::HTTP_FORBIDDEN);
                    }
                    break;
                    
                case 'branch_manager':
                    // Branch managers can view any queue in their branch
                    if ($queue->branch_id != $user->branch_id) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'You can only view queues in your branch'
                        ], Response::HTTP_FORBIDDEN);
                    }
                    break;
                    
                case 'business_owner':
                    // Business owners can view any queue in their business
                    $branch = Branch::find($queue->branch_id);
                    if (!$branch || $branch->business_id != $user->business_id) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'You can only view queues in your business'
                        ], Response::HTTP_FORBIDDEN);
                    }
                    break;
                    
                default:
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized role'
                    ], Response::HTTP_FORBIDDEN);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $queue,
                'latecomer_queues' => $queue->latecomerQueue
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            Log::error('Queue retrieval failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve queue: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $queue = Queue::find($id);
            
            if (!$queue) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Queue not found'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $user = Auth::user();
            
            // Check if user has permission to update this queue
            switch ($user->role) {
                case 'staff':
                    // Staff can only update their own queues
                    $staffId = Staff::where('user_id', $user->id)->first()?->id;
                    if (!$staffId || $queue->staff_id != $staffId) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'You can only update your own queues'
                        ], Response::HTTP_FORBIDDEN);
                    }
                    break;
                    
                case 'branch_manager':
                    // Branch managers can update any queue in their branch
                    if ($queue->branch_id != $user->branch_id) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'You can only update queues in your branch'
                        ], Response::HTTP_FORBIDDEN);
                    }
                    break;
                    
                default:
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized role'
                    ], Response::HTTP_FORBIDDEN);
            }
            
            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255',
                'scheduled_date' => 'nullable|date',
                'is_active' => 'boolean|nullable',
                'start_time' => 'nullable|date_format:H:i',
                'preferences' => 'nullable|json'
            ]);
            
            $queue->update($validatedData);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Queue updated successfully',
                'data' => $queue->fresh()
            ], Response::HTTP_OK);
            
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
            
        } catch (\Exception $e) {
            Log::error('Queue update failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update queue: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $queue = Queue::find($id);
            
            if (!$queue) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Queue not found'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $user = Auth::user();
            
            // Check if user has permission to delete this queue
            switch ($user->role) {
                case 'staff':
                    // Staff can only delete their own queues
                    $staffId = Staff::where('user_id', $user->id)->first()?->id;
                    if (!$staffId || $queue->staff_id != $staffId) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'You can only delete your own queues'
                        ], Response::HTTP_FORBIDDEN);
                    }
                    break;
                    
                case 'branch_manager':
                    // Branch managers can delete any queue in their branch
                    if ($queue->branch_id != $user->branch_id) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'You can only delete queues in your branch'
                        ], Response::HTTP_FORBIDDEN);
                    }
                    break;
                    
                default:
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized role'
                    ], Response::HTTP_FORBIDDEN);
            }
            
            $queue->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Queue deleted successfully'
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            Log::error('Queue deletion failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete queue: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
