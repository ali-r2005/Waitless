<?php

namespace App\Http\Controllers\QueueManagement;

use App\Http\Controllers\Controller;
use App\Models\Queue;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
                    $query->where('user_id', $user->id);
                    break;

                case 'business_owner':
                    $businessId = $user->business_id;
                    // Business owners can see all queues in their business
                    if (!$businessId) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Business not assigned to user'
                        ], Response::HTTP_BAD_REQUEST);
                    }

                    // Get all queues for this business

                    $query->where('business_id', $businessId);
                    break;

                default:
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized role'
                    ], Response::HTTP_FORBIDDEN);
            }


            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            $queues = $query->get();
            return response()->json([
                'status' => 'success',
                'data' => $queues,
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

            if($user->role !== 'business_owner' && $user->role !== 'staff') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized role'
                ], Response::HTTP_FORBIDDEN);
            }

            $validatedData['business_id'] = $user->business_id;
            $validatedData['user_id'] = $user->id;

            $queue = Queue::create($validatedData);
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
            // Load the queue with all necessary relationships and pivot data
            $queue = Queue::with(['users'])->find($id);

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
                    if ($queue->user_id != $user->id) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'You can only view your own queues'
                        ], Response::HTTP_FORBIDDEN);
                    }
                    break;

                case 'business_owner':
                    // Business owners can view any queue in their business
                    if ($queue->business_id != $user->business_id) {
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
                    if ($queue->user_id != $user->id) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'You can only update your own queues'
                        ], Response::HTTP_FORBIDDEN);
                    }
                    break;

                case 'business_owner':
                    // Business owners can update any queue in their business
                    if ($queue->business_id != $user->business_id) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'You can only update queues in your business'
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
                    if ($queue->user_id != $user->id) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'You can only delete your own queues'
                        ], Response::HTTP_FORBIDDEN);
                    }
                    break;

                case 'business_owner':
                    // Business owners can delete any queue in their business
                    if ($queue->business_id != $user->business_id) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'You can only delete queues in your business'
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
