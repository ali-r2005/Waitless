<?php

namespace App\Services;

use App\Models\Queue;
use App\Models\QueueUser;
use App\Models\User;
use App\Notifications\NewMessageNotification;

class QueueManagerService
{
    public function __construct(private QueueService $queueService) {}
    public function addcustumer(Queue $queue, User $user){
         $maxPosition = QueueUser::where('queue_id', $queue->id)
         ->where('status', 'waiting')
         ->max('position') ?? 0;

         $ticket_number = 'TICKET-' . ($maxPosition + 1);
         $queue->users()->attach($user->id, [
            'queue_id' => $queue->id,
            'user_id' => $user->id,
            'ticket_number' => $ticket_number,
            'position' => $maxPosition + 1,
            'status' => 'waiting'
         ]);
         $user->notify(new NewMessageNotification('You have been added to the queue ' . $queue->name . ' with ticket number ' . $ticket_number));
    }
    public function removecustumer(Queue $queue, User $user){
        if(!$queue->users()->where('queue_id', $queue->id)->where('user_id', $user->id)->where('status', 'waiting')->exists()){
            throw new \Exception('User is not in the queue');
        }
        $queue->users()->detach($user->id);
        $this->queueService->normalizePositions($queue->id);
        //here an notification for all users about there new position after the change
    }
    
    public function activateQueue(Queue $queue){
        $queue->is_active = true;
        $queue->save();
        //send message to each customer in the queue 
        //send message to the customer num 1 that his turn is now and for 2 that his turn is close
    }

    public function callNextCustomer(Queue $queue){
        $nextCustomer = $queue->users()->where('status', 'waiting')->orderBy('position')->first();
        $queue->users()->updateExistingPivot($nextCustomer->id, [
            'status' => 'serving',
            'start_serving_at' => now()
        ]);
        // $this->broadcastQueueUpdates($queue->id);
    }

    public function completeServing(Queue $queue){
        $servingCustomer = $queue->users()->where('status', 'serving')->first();
        $queue->users()->updateExistingPivot($servingCustomer->id, [
            'status' => 'served',
            'served_at' => now()
        ]);
        // $this->broadcastQueueUpdates($queue->id);
    }

    public function markCustomerAsLate(Queue $queue, User $user){
        $exists = $queue->users()->where('user_id', $user->id)->exists();
        if(!$exists) {
            throw new \Exception('User is not in the queue');
        }

        $queue->users()->updateExistingPivot($user->id, [
            'status' => 'late',
            'late_at' => now()
        ]);

        // Normalize positions since the user is no longer 'waiting'
        $this->queueService->normalizePositions($queue->id);
    }
}