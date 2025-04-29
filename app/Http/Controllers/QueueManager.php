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

class QueueManager extends Controller
{
    public function search(Request $request)
    {
        try {
            $request->validate(['name' => 'required|string']);
            
            $users = User::where('name', 'like', "%{$request->name}%")
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
            
            // Get the queue_user pivot record which contains timestamps
            $queueUser = $queue->users()
                ->where('user_id', $request->user_id)
                ->first();
                
            if (!$queueUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Customer not found in queue'
                ], Response::HTTP_NOT_FOUND);
            }
            
            $servedAt = $queueUser->pivot->served_at;
            
            // Mark the current time as completed_at
            $completedAt = now();
            
            // Calculate waiting time in seconds between served_at and completed_at
            $waitingTimeInSeconds = $completedAt->diffInSeconds($servedAt);
            
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
                ->get(['users.id', 'users.name', 'queue_user.ticket_number', 'queue_user.status', 'queue_user.position']);
            
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
            $averageServiceTimeInSeconds = 300;
            
            if ($recentServedCustomers->isNotEmpty()) {
                $totalWaitingTime = $recentServedCustomers->sum('waiting_time');
                $averageServiceTimeInSeconds = $totalWaitingTime / $recentServedCustomers->count();
            }
            
            // Find the current customer being served (first in queue or with status 'serving')
            $currentCustomer = $queueUsers->firstWhere('status', 'serving') ?? $queueUsers->first();
            
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
                $estimatedWaitingTime = $position * $averageServiceTimeInSeconds;
                
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
            $queue->latecomerQueue()->users()->attach($request->user_id);
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
            $lateCustomers = $queue->latecomerQueue()->users()->get();
            return response()->json([
                'status' => 'success',
                'data' => $lateCustomers
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
            $queue->latecomerQueue()->users()->detach($request->user_id);
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
    
}

