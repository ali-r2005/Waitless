<?php

namespace App\Services;

use App\Models\QueueUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Queue;
use App\Events\SendUpdate;

class QueueService
{
     public function normalizePositions($queueId)
    {
        $customers = QueueUser::where('queue_id', $queueId)
            ->where('status', 'waiting')
            ->orderBy('position')
            ->get();

        foreach ($customers as $index => $customer) {
            $customer->position = $index + 1;
            $customer->save();
        }
    }

    public function move($newPosition, $id)
    {
        $customer = QueueUser::findOrFail($id);
        $queueId = $customer->queue_id;

        // Active statuses that still hold a position in the queue
        $activeStatuses = ['waiting', 'serving'];

        // Early exit: nothing to do if position is unchanged
        if ($newPosition === $customer->position) {
            return;
        }

        // Clamp to valid bounds: can't go below 1 or beyond the total active queue size
        $queueSize = QueueUser::where('queue_id', $queueId)
            ->whereIn('status', $activeStatuses)
            ->count();
        $newPosition = max(1, min($newPosition, $queueSize));

        // Another early exit after clamping
        if ($newPosition === $customer->position) {
            return;
        }

        DB::transaction(function () use ($customer, $newPosition, $queueId, $activeStatuses) {
            if ($newPosition < $customer->position) {
                // Moving up: push others down
                QueueUser::where('queue_id', $queueId)
                    ->whereIn('status', $activeStatuses)
                    ->whereBetween('position', [$newPosition, $customer->position - 1])
                    ->increment('position');
            } else {
                // Moving down: pull others up
                QueueUser::where('queue_id', $queueId)
                    ->whereIn('status', $activeStatuses)
                    ->whereBetween('position', [$customer->position + 1, $newPosition])
                    ->decrement('position');
            }

            // Set the customer's new position
            $customer->position = $newPosition;
            $customer->save();
        });
    }

    public function broadcastQueueUpdates($queueId)
    {
        try {
            $queue = Queue::findOrFail($queueId);

            if (!$queue->is_active) {
                return;
            }

            $queueUsers = $queue->users()
                ->where('queue_user.status', 'waiting')
                ->orWhere('queue_user.status', 'serving')
                ->orderBy('queue_user.position')
                ->get(['users.id', 'users.name', 'users.phone', 'users.email', 'queue_user.id as queue_user_id', 'queue_user.ticket_number', 'queue_user.status', 'queue_user.position']);

            if ($queueUsers->isEmpty()) {
                return;
            }

            // Queue length (active users only)
            $queueLength = $queueUsers->count();

            // Calculate N - the number of recent customers to consider
            $alpha = 0.5; // α is a fraction equal to 0.5
            $n = max(1, round($alpha * $queueLength)); // At least 1 customer

            // Get the average waiting time from the most recent N served customers
            $recentServedCustomers = $queue->users()
                ->where('queue_user.status', 'served')
                ->orderByPivot('served_at', 'desc')
                ->take($n)
                ->get();

            // Default value if no served customers yet
            $averageServiceTimeInSeconds = 300; // Default 5 minutes per customer

            if ($recentServedCustomers->isNotEmpty()) {
                // time between start_serving_at and served_at
                $totalServiceTime = $recentServedCustomers->sum(function ($customer) {
                    if ($customer->start_serving_at && $customer->served_at) {
                        return $customer->served_at->diffInSeconds($customer->start_serving_at);
                    }
                    return 0;
                });
                $averageServiceTimeInSeconds = $totalServiceTime / $recentServedCustomers->count();

                // Ensure we have a reasonable minimum value (at least 30 seconds)
                $averageServiceTimeInSeconds = max(30, $averageServiceTimeInSeconds);
            }

            // Update the queue's average waiting time
            $queue->update(['average_waiting_time' => $averageServiceTimeInSeconds]);

            // Find the customer currently being served (separate query — $queueUsers only has 'waiting')
            $currentQueueUser = QueueUser::where('queue_id', $queueId)
                ->where('status', 'serving')
                ->first();

            // Load the related User so we can access name/email/phone for the staff payload
            $currentCustomer = $currentQueueUser ? $currentQueueUser->user : null;

            // Persist EWT = 0 for the serving customer so the column stays consistent
            if ($currentQueueUser) {
                $currentQueueUser->update(['estimated_waiting_time' => 0]);
            }

            // Determine the queue state for UI display
            $queueState = 'active';
            if ($queue->is_paused) {
                $queueState = 'paused';
            } elseif (!$queue->is_active) {
                $queueState = 'inactive';
            } elseif (!$currentQueueUser) {
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
                    'id' => $currentQueueUser->user_id,
                    'name' => $currentCustomer->name,
                    'phone' => $currentCustomer->phone,
                    'email' => $currentCustomer->email,
                    'ticket_number' => $currentQueueUser->ticket_number,
                    'status' => $currentQueueUser->status,
                ] : null,
                'total_customers' => $queueLength,
                'average_service_time' => $averageServiceTimeInSeconds,
                'waiting_customers' => $queueUsers->where('status', 'waiting')->count(), 
                'next_available_customer' => $queueUsers->where('status', 'waiting')->first() ? [
                    'id' => $queueUsers->where('status', 'waiting')->first()->id,
                    'name' => $queueUsers->where('status', 'waiting')->first()->name,
                    'ticket_number' => $queueUsers->where('status', 'waiting')->first()->ticket_number,
                ] : null,
                'timestamp' => now()->toIso8601String(),
            ];

            // Broadcast update to staff and branch managers
            event(new \App\Events\StaffQueueUpdate($staffQueueData));

            // For each waiting customer in the queue, calculate & persist EWT, then broadcast
            foreach ($queueUsers as $user) {
                // customersAhead = position - 1 (position is 1-based)
                $customersAhead = $user->position - 1;

                $estimatedWaitingTime = 0;

                if ($queue->is_paused) {
                    $estimatedWaitingTime = -1;
                }
                elseif($currentQueueUser && $currentQueueUser->id == $user->queue_user_id){
                    $estimatedWaitingTime = 0;
                }
                elseif ($currentQueueUser) {
                    // Someone is actively being served.
                    // First waiting customer (customersAhead == 1): ~half the avg service time remains.
                    // Subsequent customers: full time per extra slot.
                    $estimatedWaitingTime = (($customersAhead - 1) * $averageServiceTimeInSeconds)
                        + ($averageServiceTimeInSeconds / 2);
                } else {
                    // Nobody is being served yet; full average service time per slot ahead.
                    $estimatedWaitingTime = ($customersAhead + 1) * $averageServiceTimeInSeconds;
                }

                // Persist the calculated estimated waiting time to the queue_user row
                QueueUser::where('id', $user->queue_user_id)
                    ->update(['estimated_waiting_time' => $estimatedWaitingTime]);

                // Prepare the update payload for this specific user
                $update = [
                    'receiver_id' => $user->id,
                    'queue_id' => $queueId,
                    'queue_name' => $queue->name,
                    'queue_state' => $queueState,
                    'is_paused' => $queue->is_paused,
                    'estimated_waiting_time' => $estimatedWaitingTime,
                    'current_customer' => $currentCustomer ? [
                        'ticket_number' => $currentQueueUser->ticket_number,
                    ] : null,
                    'position' => $user->position,
                    'ticket_number' => $user->ticket_number,
                    'status' => $user->status,
                ];

                // Broadcast the update to this specific user's private channel
                event(new SendUpdate($update));
            }

            Log::info("Queue {$queueId} broadcast complete. Avg service time: {$averageServiceTimeInSeconds}s, Active users: {$queueLength}");

        } catch (\Exception $e) {
            Log::error('Failed to broadcast queue updates: ' . $e->getMessage());
        }
    }

    public function checker(Queue $queue, bool $active, bool|null $pause = null, string $message ="Exception in queue state" ){
        Log::info('queue active: '.$queue->is_active . ' queue paused: ' . $queue->is_paused . ' active: ' . $active . ' pause: ' . $pause);
        if ($pause === null) {
            if ($queue->is_active != $active) {
                throw new \Exception($message);
            }
        } else {
            if ($queue->is_active != $active || $queue->is_paused != $pause) {
                throw new \Exception($message);
            }
        }
    }
}
