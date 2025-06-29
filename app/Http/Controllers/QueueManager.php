<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Queue;
use App\Models\QueueUser;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;
use App\Notifications\NewMessageNotification;
use App\Events\SendUpdate;
use App\Models\ServedCustomer;
use App\Models\Branch;
use Carbon\Carbon;
use App\Models\LatecomerQueue;

class QueueManager extends Controller
{
    
    public function addCustomerToQueue(Request $request){
        try {
            $request->validate([
                'queue_id' => 'required|exists:queues,id',
                'user_id' => 'required|exists:users,id'
            ]);
            $queue = Queue::find($request->queue_id);
            $maxPosition = QueueUser::where('queue_id', $request->queue_id)->max('position') ?? 0;
            $ticket_number = 'TICKET-' . ($maxPosition + 1);
            $queue->users()->attach($request->user_id, [
                'ticket_number' => $ticket_number,
                'position' => $maxPosition + 1,
                'status' => 'waiting'
            ]);
            $user = User::find($request->user_id);
            $user->notify(new NewMessageNotification('You have been added to the queue ' . $queue->name . ' with ticket number ' . $ticket_number));
            return response()->json([
                'status' => 'success',
                'message' => 'Customer added to queue successfully'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Failed to add customer to queue: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add customer to queue'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function removeCustomerFromQueue(Request $request){
        try {
            $request->validate([
                'queue_id' => 'required|exists:queues,id',
                'user_id' => 'required|exists:users,id'
            ]);
            $queue = Queue::find($request->queue_id);
            $queue->users()->detach($request->user_id);
            $this->normalizePositions($request->queue_id);
            $this->broadcastQueueUpdates($queue->id);
            return response()->json([
                'status' => 'success',
                'message' => 'Customer removed from queue successfully'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Failed to remove customer from queue: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove customer from queue'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function normalizePositions($queueId)
    {
        $customers = QueueUser::where('queue_id', $queueId)
            ->orderBy('position')
            ->get();

        foreach ($customers as $index => $customer) {
            $customer->position = $index + 1;
            $customer->save();
        }
    }

    //PATCH /api/queue-customers/{id}/move
    //BODY: { "new_position": 3 }
    public function move(Request $request, $id)
    {
        $customer = QueueUser::findOrFail($id);
        $queueId = $customer->queue_id;
        $newPosition = (int) $request->input('new_position');

        \DB::transaction(function () use ($customer, $newPosition, $queueId) {
            if ($newPosition < $customer->position) {
                // Moving up: push down others
                QueueUser::where('queue_id', $queueId)
                    ->whereBetween('position', [$newPosition, $customer->position - 1])
                    ->increment('position');
            } elseif ($newPosition > $customer->position) {
                // Moving down: pull up others
                QueueUser::where('queue_id', $queueId)
                    ->whereBetween('position', [$customer->position + 1, $newPosition])
                    ->decrement('position');
            }

            // Finally, set the new position
            $customer->position = $newPosition;
            $customer->save();
        });

        return response()->json(['message' => 'Customer moved successfully.']);
    }

    /**
     * Get customers served today with statistics based on user role
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCustomersServedToday(Request $request)
    {
        try {
            $user = auth()->user();
            $today = Carbon::today();
            $tomorrow = Carbon::tomorrow();
            
            // Base query for served customers
            $query = ServedCustomer::with('user', 'queue')
                ->whereBetween('created_at', [$today, $tomorrow])
                ->orderBy('created_at', 'desc');
            
            // Filter based on user role
            if ($user->role === 'business_owner') {
                // For business owner: get all served customers in the business
                $businessId = $user->business_id;
                
                // Get all queues from all branches in the business
                $branchIds = Branch::where('business_id', $businessId)->pluck('id');
                $queueIds = Queue::whereIn('branch_id', $branchIds)->pluck('id');
                
                $query->whereIn('queue_id', $queueIds);
                
                $responseTitle = 'Business-wide Served Customers';
                
            } elseif ($user->role === 'branch_manager') {
                // For branch manager: get all served customers in their branch
                $branchId = $user->branch_id;
                
                // Get all queues from the branch
                $queueIds = Queue::where('branch_id', $branchId)->pluck('id');
                
                $query->whereIn('queue_id', $queueIds);
                
                $responseTitle = 'Branch Served Customers';
                
            } elseif ($user->role === 'staff') {
                // If a specific queue_id is provided
                if ($request->has('queue_id')) {
                    $request->validate([
                        'queue_id' => 'required|exists:queues,id'
                    ]);
                    
                    // Get the specific queue's served customers
                    $queue = Queue::find($request->queue_id);
                    $query->where('queue_id', $queue->id);
                    
                    $responseTitle = 'Queue Served Customers';
                } else {
                    // Get customers served by this staff member across all queues they manage
                    $staffUser = $user->staff;
                    $staffId = $staffUser->id;
                    
                    // Find queues where this staff is assigned
                    $queueIds = Queue::where('staff_id', $staffId)->pluck('id');
                    
                    $query->whereIn('queue_id', $queueIds);
                    
                    $responseTitle = 'Staff Served Customers';
                }
            } else {
                // For regular users (customers), only return their own served history
                $query->where('user_id', $user->id);
                $responseTitle = 'Your Service History';
            }
            
            // Execute the query
            $servedCustomers = $query->get();
            
            // Calculate statistics
            $totalServed = $servedCustomers->count();
            $averageWaitingTime = $totalServed > 0 ? $servedCustomers->avg('waiting_time') : 0;
            
            // Group statistics differently based on user role
            $statistics = [
                'total_served' => $totalServed,
                'average_waiting_time' => round($averageWaitingTime, 2),
                'date' => $today->toDateString(),
            ];
            
            if ($user->role === 'business_owner') {
                // For business owner: group by branch and then by queue
                $branchStats = [];
                
                // First group by branch
                $branchQueues = [];
                foreach ($servedCustomers as $customer) {
                    $queue = $customer->queue;
                    if (!$queue) continue;
                    
                    $branchId = $queue->branch_id;
                    if (!isset($branchQueues[$branchId])) {
                        $branchQueues[$branchId] = [];
                    }
                    
                    if (!isset($branchQueues[$branchId][$queue->id])) {
                        $branchQueues[$branchId][$queue->id] = [];
                    }
                    
                    $branchQueues[$branchId][$queue->id][] = $customer;
                }
                
                // Generate branch-level statistics
                foreach ($branchQueues as $branchId => $queues) {
                    $branch = Branch::find($branchId);
                    if (!$branch) continue;
                    
                    $branchCustomers = collect(array_merge(...array_values($queues)));
                    $queueStats = [];
                    
                    // Generate queue-level statistics for this branch
                    foreach ($queues as $queueId => $customers) {
                        $queueCustomers = collect($customers);
                        $queue = Queue::find($queueId);
                        
                        $queueStats[] = [
                            'queue_id' => $queueId,
                            'queue_name' => $queue ? $queue->name : 'Unknown Queue',
                            'total_served' => count($customers),
                            'average_waiting_time' => $queueCustomers->count() > 0 ? 
                                round($queueCustomers->avg('waiting_time'), 2) : 0,
                        ];
                    }
                    
                    $branchStats[] = [
                        'branch_id' => $branchId,
                        'branch_name' => $branch->name,
                        'total_served' => $branchCustomers->count(),
                        'average_waiting_time' => $branchCustomers->count() > 0 ? 
                            round($branchCustomers->avg('waiting_time'), 2) : 0,
                        'queues' => $queueStats
                    ];
                }
                
                $statistics['branches'] = $branchStats;
                
            } elseif ($user->role === 'branch_manager' || $user->role === 'staff') {
                // For branch manager and staff: group by queue only
                $queueStats = [];
                $queueGroups = $servedCustomers->groupBy('queue_id');
                
                foreach ($queueGroups as $queueId => $customers) {
                    $queue = Queue::find($queueId);
                    $queueStats[] = [
                        'queue_id' => $queueId,
                        'queue_name' => $queue ? $queue->name : 'Unknown Queue',
                        'total_served' => $customers->count(),
                        'average_waiting_time' => round($customers->avg('waiting_time'), 2),
                    ];
                }
                
                $statistics['queues'] = $queueStats;
            }
            
            return response()->json([
                'status' => 'success',
                'title' => $responseTitle,
                'data' => [
                    'served_customers' => $servedCustomers,
                    'statistics' => $statistics
                ]
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function getQueueCustomers(Request $request){
        try {
            $request->validate([
                'queue_id' => 'required|exists:queues,id'
            ]);
            $queue = Queue::find($request->queue_id);
            $customers = $queue->users()->orderBy('position')->get();
            return response()->json([
                'status' => 'success',
                'data' => $customers
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Failed to get queue customers: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get queue customers'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function activateQueue(Request $request){
        try {
            $request->validate([
                'queue_id' => 'required|exists:queues,id'
            ]);
            $queue = Queue::find($request->queue_id);
            $queue->is_active = true;
            $queue->save();
            //send message to each customer in the queue 
            //send message to the customer num 1 that his turn is now and for 2 that his turn is close
            return response()->json([
                'status' => 'success',
                'message' => 'Queue activated successfully'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Failed to activate queue: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to activate queue'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    public function completeServing(Request $request){
        try {
            $request->validate([
                'queue_id' => 'required|exists:queues,id',
                'user_id' => 'required|exists:users,id'
            ]);
            $queue = Queue::find($request->queue_id);
            
            // Get the queue_user pivot record directly from the pivot table
            $pivot = \DB::table('queue_user')
                ->where('queue_id', $queue->id)
                ->where('user_id', $request->user_id)
                ->first();

            $servedAt = $pivot ? $pivot->served_at : null;
            Log::info('servedAt: ' . $servedAt);
            
            // Mark the current time as completed_at
            $completedAt = now();
            
            // Calculate waiting time in seconds between served_at and completed_at
            $waitingTimeInSeconds = Carbon::parse($servedAt)->diffInSeconds($completedAt);
            Log::info('waitingTimeInSeconds: ' . $waitingTimeInSeconds);
            
            // Create the served customer record
            $servedCustomer = ServedCustomer::create([
                'queue_id' => $queue->id,
                'user_id' => $request->user_id,
                'waiting_time' => $waitingTimeInSeconds
            ]);
            
            // Detach the user from queue
            $queue->users()->detach($request->user_id);
            $this->normalizePositions($queue->id);
            
            // Broadcast updated queue information to all remaining customers
            $this->broadcastQueueUpdates($queue->id);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Customer served successfully',
                'data' => [
                    'waiting_time' => $waitingTimeInSeconds,
                    'served_at' => $servedAt,
                    'completed_at' => $completedAt
                ]
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Failed to complete serving: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to complete serving'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Broadcast queue updates to all customers in a specific queue
     * 
     * @param int $queueId The ID of the queue to broadcast updates for
     * @return void
     */
    public function broadcastQueueUpdates($queueId)
    {
        try {
            $queue = Queue::findOrFail($queueId);
            
            // Get all users in this queue ordered by their position time
            $queueUsers = $queue->users()
                ->orderBy('position')
                ->get(['users.id', 'users.name', 'users.phone', 'users.email', 'queue_user.ticket_number', 'queue_user.status', 'queue_user.position']);
            
            if ($queueUsers->isEmpty()) {
                return;
            }
            
            // Queue length
            $queueLength = $queueUsers->count();
            
            // Calculate N - the number of recent customers to consider
            $alpha = 0.5; // α is a fraction equal to 0.5
            $n = max(1, round($alpha * $queueLength)); // At least 1 customer
            
            // Get the average waiting time from the most recent N served customers
            $recentServedCustomers = ServedCustomer::where('queue_id', $queueId)
                ->latest()
                ->take($n)
                ->get();
            
            // Default value if no served customers yet
            $averageServiceTimeInSeconds = 300; // Default 5 minutes per customer
            
            if ($recentServedCustomers->isNotEmpty()) {
                // The 'waiting_time' in ServedCustomer is actually the service time (time between served_at and completion)
                $totalServiceTime = $recentServedCustomers->sum('waiting_time');
                $averageServiceTimeInSeconds = $totalServiceTime / $recentServedCustomers->count();
                
                // Ensure we have a reasonable minimum value (at least 30 seconds)
                $averageServiceTimeInSeconds = max(30, $averageServiceTimeInSeconds);
            }
            
            // Find the current customer being served (first in queue or with status 'serving')
            $currentCustomer = $queueUsers->firstWhere('status', 'serving') ?? $queueUsers->first();
            
            // Determine the queue state for UI display
            $queueState = 'active';
            if ($queue->is_paused) {
                $queueState = 'paused';
            } elseif (!$queue->is_active) {
                $queueState = 'inactive';
            } elseif (!$currentCustomer || $currentCustomer->status !== 'serving') {
                $queueState = 'ready_to_call'; // Queue is active but no one is being served yet
            }
            
            // Prepare data for staff and branch managers
            $staffQueueData = [
                'queue_id' => $queueId,
                'queue_name' => $queue->name,
                'is_active' => $queue->is_active,
                'is_paused' => $queue->is_paused,
                'queue_state' => $queueState,
                'current_serving' => $currentCustomer ? [
                    'id' => $currentCustomer->id,
                    'name' => $currentCustomer->name,
                    'phone' => $currentCustomer->phone,
                    'email' => $currentCustomer->email,
                    'ticket_number' => $currentCustomer->ticket_number,
                    'status' => $currentCustomer->status
                ] : null,
                'total_customers' => $queueUsers->count(),
                'average_service_time' => $averageServiceTimeInSeconds,
                'waiting_customers' => $queueUsers->where('status', 'waiting')->count(),
                'next_available_customer' => $queueUsers->where('status', 'waiting')->first() ? [
                    'id' => $queueUsers->where('status', 'waiting')->first()->id,
                    'name' => $queueUsers->where('status', 'waiting')->first()->name,
                    'ticket_number' => $queueUsers->where('status', 'waiting')->first()->ticket_number
                ] : null,
                'timestamp' => now()->toIso8601String()
            ];
            
            // Broadcast update to staff and branch managers
            event(new \App\Events\StaffQueueUpdate($staffQueueData));
            
            // For each customer in the queue, broadcast their position and estimated wait time
            foreach ($queueUsers as $index => $user) {
                // Calculate position (0 for current customer)
                $position = 0;
                if ($user->id != $currentCustomer->id) {
                    $position = $queueUsers->search(function($item) use ($user) {
                        return $item->id === $user->id;
                    });
                }
                
                // Calculate estimated waiting time based on position and average service time
                $estimatedWaitingTime = 0;
                
                // If queue is paused, waiting time is indefinite (represented by -1)
                if ($queue->is_paused) {
                    $estimatedWaitingTime = -1;
                } else if ($position > 0) {
                    // If there's a customer being served, adjust the calculation
                    if ($currentCustomer->status === 'serving') {
                        // For the first waiting customer, estimate half the average service time
                        // For others, full time for each position ahead of them
                        if ($position === 1) {
                            $estimatedWaitingTime = $averageServiceTimeInSeconds / 2;
                        } else {
                            $estimatedWaitingTime = (($position - 1) * $averageServiceTimeInSeconds) + ($averageServiceTimeInSeconds / 2);
                        }
                    } else {
                        // No customer is currently being served, so use the original calculation
                        if($position === 0){
                            $estimatedWaitingTime = $averageServiceTimeInSeconds / 2;
                        } else {
                            $estimatedWaitingTime = $position * $averageServiceTimeInSeconds;
                        }
                    }
                }
                
                // Get remaining customers count
                $customersAhead = $position;
                $totalCustomers = $queueUsers->count();
                
                // Prepare the update data
                $update = [
                    'type' => 'queue_update',
                    'receiver_id' => $user->id,
                    'data' => [
                        'queue_id' => $queueId,
                        'queue_name' => $queue->name,
                        'queue_state' => $queueState,
                        'is_paused' => $queue->is_paused,
                        'estimated_waiting_time' => $estimatedWaitingTime,
                        'average_service_time' => $averageServiceTimeInSeconds,
                        'current_customer' => [
                            'id' => $currentCustomer->id,
                            'name' => $currentCustomer->name,
                            'ticket_number' => $currentCustomer->ticket_number
                        ],
                        'position' => $position,
                        'customers_ahead' => $customersAhead,
                        'total_customers' => $totalCustomers
                    ]
                ];
                
                // Broadcast the update to this specific user
                event(new SendUpdate($update));
                Log::info('The average service time is ' . $averageServiceTimeInSeconds . ' seconds');
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to broadcast queue updates: ' . $e->getMessage());
        }
    }
    public function callNextCustomer(Request $request){
        try {
            $request->validate([
                'queue_id' => 'required|exists:queues,id'
            ]);
            $queue = Queue::find($request->queue_id);
            $nextCustomer = $queue->users()->where('status', 'waiting')->orderBy('position')->first();
            $queue->users()->updateExistingPivot($nextCustomer->id, [
                'status' => 'serving',
                'served_at' => now()
            ]);
            $this->broadcastQueueUpdates($queue->id);
            return response()->json([
                'status' => 'success',
                'message' => 'Next customer called successfully',
                'data' => $nextCustomer
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            Log::error('Failed to call next customer: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to call next customer'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
 
    public function lateCustomer(Request $request){
        try {
            $request->validate([
                'queue_id' => 'required|exists:queues,id',
                'user_id' => 'required|exists:users,id'
            ]);
            $queue = Queue::find($request->queue_id);
            $queue->users()->updateExistingPivot($request->user_id, [
                'status' => 'late'
            ]);
            $latecomerQueue = $queue->latecomerQueue;
            if ($latecomerQueue) {
                $latecomerQueue->users()->attach($request->user_id);
            }
            $this->broadcastQueueUpdates($queue->id);
            //send notification to the customer
            $user = User::find($request->user_id);
            $user->notify(new NewMessageNotification('You have been marked as late in the queue ' . $queue->name));
            return response()->json([
                'status' => 'success',
                'message' => 'Customer marked as late successfully'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Failed to mark customer as late: ' . $e->getMessage());
        }
    }
    public function getLateCustomers(Request $request){
        try {
            $request->validate([
                'queue_id' => 'required|exists:queues,id'
            ]);
            $queue = Queue::find($request->queue_id);
            $latecomerQueue = $queue->latecomerQueue;
            if ($latecomerQueue) {
                $users = $latecomerQueue->users()->get();
            }
            return response()->json([
                'status' => 'success',
                'data' => $users
            ], Response::HTTP_OK);  
        } catch (\Exception $e) {
            Log::error('Failed to get late customers: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get late customers'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function reinsertCustomer(Request $request){
        try{
            //add wich position to reisert the customer on it and the queue id
            $request->validate([
                'queue_id' => 'required|exists:queues,id',
                'user_id' => 'required|exists:users,id',
                'position' => 'required|integer'
            ]);
            $queue = Queue::find($request->queue_id);
            $latecomerQueue = $queue->latecomerQueue;
            if ($latecomerQueue) {
                $latecomerQueue->users()->detach($request->user_id);
            }
            $queue->users()->updateExistingPivot($request->user_id, [
                'status' => 'waiting'
            ]);
            
            // Get the queue_user record
            $queueUser = QueueUser::where('queue_id', $request->queue_id)
                ->where('user_id', $request->user_id)
                ->first();
                
            if ($queueUser) {
                // Create a new request with the new_position parameter
                $moveRequest = new Request();
                $moveRequest->merge(['new_position' => $request->position]);
                $this->move($moveRequest, $queueUser->id);
            }
            
            $this->normalizePositions($queue->id);
            $this->broadcastQueueUpdates($queue->id);
            return response()->json([
                'status' => 'success',
                'message' => 'Customer reinserted in the queue successfully'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Failed to reinsert customer in the queue: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reinsert customer in the queue'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Pause a queue
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function pauseQueue(Request $request)
    {
        try {
            $request->validate([
                'queue_id' => 'required|exists:queues,id',
                'reason' => 'nullable|string|max:255'
            ]);
            
            $queue = Queue::findOrFail($request->queue_id);
            
            // Only active queues can be paused
            if (!$queue->is_active) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot pause an inactive queue'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // If already paused, return success but with a message
            if ($queue->is_paused) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Queue is already paused'
                ], Response::HTTP_OK);
            }
            
            // Pause the queue
            $queue->is_paused = true;
            $queue->save();
            
            // Broadcast the pause status to all customers in the queue
            $this->broadcastQueueUpdates($queue->id);
            
            // Log the pause action
            $reason = $request->reason ?? 'No reason provided';
            Log::info("Queue {$queue->name} (ID: {$queue->id}) paused. Reason: {$reason}");
            
            return response()->json([
                'status' => 'success',
                'message' => 'Queue paused successfully'
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            Log::error('Failed to pause queue: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to pause queue'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Resume a paused queue
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resumeQueue(Request $request)
    {
        try {
            $request->validate([
                'queue_id' => 'required|exists:queues,id'
            ]);
            
            $queue = Queue::findOrFail($request->queue_id);
            
            // Only paused queues can be resumed
            if (!$queue->is_paused) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Queue is not paused'
                ], Response::HTTP_OK);
            }
            
            // Resume the queue
            $queue->is_paused = false;
            $queue->save();
            
            // Broadcast the resumed status to all customers in the queue
            $this->broadcastQueueUpdates($queue->id);
            
            // Log the resume action
            Log::info("Queue {$queue->name} (ID: {$queue->id}) resumed");
            
            return response()->json([
                'status' => 'success',
                'message' => 'Queue resumed successfully'
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            Log::error('Failed to resume queue: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to resume queue'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
}
