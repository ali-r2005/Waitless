<?php

namespace App\Services;

use App\Models\QueueUser;
use Illuminate\Support\Facades\DB;

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

        DB::transaction(function () use ($customer, $newPosition, $queueId) {
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

        return ['message' => 'Customer moved successfully.'];
    }

    //   public function broadcastQueueUpdates($queueId)
    // {
    //     try {
    //         $queue = Queue::findOrFail($queueId);

    //         // Get all users in this queue ordered by their position time
    //         $queueUsers = $queue->users()
    //             ->orderBy('position')
    //             ->get(['users.id', 'users.name', 'users.phone', 'users.email', 'queue_user.ticket_number', 'queue_user.status', 'queue_user.position']);

    //         if ($queueUsers->isEmpty()) {
    //             return;
    //         }

    //         // Queue length
    //         $queueLength = $queueUsers->count();

    //         // Calculate N - the number of recent customers to consider
    //         $alpha = 0.5; // α is a fraction equal to 0.5
    //         $n = max(1, round($alpha * $queueLength)); // At least 1 customer

    //         // Get the average waiting time from the most recent N served customers
    //         $recentServedCustomers = ServedCustomer::where('queue_id', $queueId)
    //             ->latest()
    //             ->take($n)
    //             ->get();

    //         // Default value if no served customers yet
    //         $averageServiceTimeInSeconds = 300; // Default 5 minutes per customer

    //         if ($recentServedCustomers->isNotEmpty()) {
    //             // The 'waiting_time' in ServedCustomer is actually the service time (time between served_at and completion)
    //             $totalServiceTime = $recentServedCustomers->sum('waiting_time');
    //             $averageServiceTimeInSeconds = $totalServiceTime / $recentServedCustomers->count();

    //             // Ensure we have a reasonable minimum value (at least 30 seconds)
    //             $averageServiceTimeInSeconds = max(30, $averageServiceTimeInSeconds);
    //         }

    //         // Find the current customer being served (first in queue or with status 'serving')
    //         $currentCustomer = $queueUsers->firstWhere('status', 'serving') ?? $queueUsers->first();

    //         // Determine the queue state for UI display
    //         $queueState = 'active';
    //         if ($queue->is_paused) {
    //             $queueState = 'paused';
    //         } elseif (!$queue->is_active) {
    //             $queueState = 'inactive';
    //         } elseif (!$currentCustomer || $currentCustomer->status !== 'serving') {
    //             $queueState = 'ready_to_call'; // Queue is active but no one is being served yet
    //         }

    //         // Prepare data for staff and branch managers
    //         $staffQueueData = [
    //             'queue_id' => $queueId,
    //             'queue_name' => $queue->name,
    //             'is_active' => $queue->is_active,
    //             'is_paused' => $queue->is_paused,
    //             'queue_state' => $queueState,
    //             'current_serving' => $currentCustomer ? [
    //                 'id' => $currentCustomer->id,
    //                 'name' => $currentCustomer->name,
    //                 'phone' => $currentCustomer->phone,
    //                 'email' => $currentCustomer->email,
    //                 'ticket_number' => $currentCustomer->ticket_number,
    //                 'status' => $currentCustomer->status
    //             ] : null,
    //             'total_customers' => $queueUsers->count(),
    //             'average_service_time' => $averageServiceTimeInSeconds,
    //             'waiting_customers' => $queueUsers->where('status', 'waiting')->count(),
    //             'next_available_customer' => $queueUsers->where('status', 'waiting')->first() ? [
    //                 'id' => $queueUsers->where('status', 'waiting')->first()->id,
    //                 'name' => $queueUsers->where('status', 'waiting')->first()->name,
    //                 'ticket_number' => $queueUsers->where('status', 'waiting')->first()->ticket_number
    //             ] : null,
    //             'timestamp' => now()->toIso8601String()
    //         ];

    //         // Broadcast update to staff and branch managers
    //         event(new \App\Events\StaffQueueUpdate($staffQueueData));

    //         // For each customer in the queue, broadcast their position and estimated wait time
    //         foreach ($queueUsers as $index => $user) {
    //             // Calculate position (0 for current customer)
    //             $position = 0;
    //             if ($user->id != $currentCustomer->id) {
    //                 $position = $queueUsers->search(function($item) use ($user) {
    //                     return $item->id === $user->id;
    //                 });
    //             }

    //             // Calculate estimated waiting time based on position and average service time
    //             $estimatedWaitingTime = 0;

    //             // If queue is paused, waiting time is indefinite (represented by -1)
    //             if ($queue->is_paused) {
    //                 $estimatedWaitingTime = -1;
    //             } else if ($position > 0) {
    //                 // If there's a customer being served, adjust the calculation
    //                 if ($currentCustomer->status === 'serving') {
    //                     // For the first waiting customer, estimate half the average service time
    //                     // For others, full time for each position ahead of them
    //                     if ($position === 1) {
    //                         $estimatedWaitingTime = $averageServiceTimeInSeconds / 2;
    //                     } else {
    //                         $estimatedWaitingTime = (($position - 1) * $averageServiceTimeInSeconds) + ($averageServiceTimeInSeconds / 2);
    //                     }
    //                 } else {
    //                     // No customer is currently being served, so use the original calculation
    //                     if($position === 0){
    //                         $estimatedWaitingTime = $averageServiceTimeInSeconds / 2;
    //                     } else {
    //                         $estimatedWaitingTime = $position * $averageServiceTimeInSeconds;
    //                     }
    //                 }
    //             }

    //             // Get remaining customers count
    //             $customersAhead = $position;
    //             $totalCustomers = $queueUsers->count();

    //             // Prepare the update data
    //             $update = [
    //                 'type' => 'queue_update',
    //                 'receiver_id' => $user->id,
    //                 'data' => [
    //                     'queue_id' => $queueId,
    //                     'queue_name' => $queue->name,
    //                     'queue_state' => $queueState,
    //                     'is_paused' => $queue->is_paused,
    //                     'estimated_waiting_time' => $estimatedWaitingTime,
    //                     'average_service_time' => $averageServiceTimeInSeconds,
    //                     'current_customer' => [
    //                         'id' => $currentCustomer->id,
    //                         'name' => $currentCustomer->name,
    //                         'ticket_number' => $currentCustomer->ticket_number
    //                     ],
    //                     'position' => $position,
    //                     'customers_ahead' => $customersAhead,
    //                     'total_customers' => $totalCustomers
    //                 ]
    //             ];

    //             // Broadcast the update to this specific user
    //             event(new SendUpdate($update));
    //             Log::info('The average service time is ' . $averageServiceTimeInSeconds . ' seconds');
    //         }

    //     } catch (\Exception $e) {
    //         Log::error('Failed to broadcast queue updates: ' . $e->getMessage());
    //     }
    // }
}
