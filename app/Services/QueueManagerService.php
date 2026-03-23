<?php

namespace App\Services;

use App\Models\Queue;
use App\Models\QueueUser;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Events\SendUpdate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

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
        $update = [
            'type' => 'queue_update',
            'receiver_id' => $user->id,
            'queue_id' => $queue->id,
            'queue_name' => $queue->name,
            'ticket_number' => $ticket_number,
            'position' => $maxPosition + 1,
            'status' => 'waiting'
        ];
        Log::info('Queue update', $update);
        event(new SendUpdate($update));
    }
    public function removecustumer(QueueUser $queueUser){
        $user = Auth::user();
        if($queueUser->user_id !== $user->id && $user->role === 'customer'){
            throw new \Exception('You are not authorized to remove this customer from the queue');
        }
        $queueId = $queueUser->queue_id;
        $queueUser->delete();
        $this->queueService->normalizePositions($queueId);
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
        $this->queueService->normalizePositions($queue->id);
        // $this->broadcastQueueUpdates($queue->id);
    }

    public function markCustomerAsLate(QueueUser $queueUser){
        $queueId = $queueUser->queue_id;
        $queueUser->update([
            'status' => 'late',
            'late_at' => now(),
            'position' => null
        ]);

        // Normalize positions since the user is no longer 'waiting'
        $this->queueService->normalizePositions($queueId);
    }

    public function reinsertCustomer(QueueUser $queueUser, $position){
        if ($queueUser->status !== 'late') {
            throw new \Exception('Only late customers can be reinserted');
        }

        $queueId = $queueUser->queue_id;

        // Step 1: Place the customer back at the end of the waiting queue
        $maxPosition = QueueUser::where('queue_id', $queueId)
            ->where('status', 'waiting')
            ->max('position') ?? 0;

        $queueUser->status = 'waiting';
        $queueUser->position = $maxPosition + 1;
        $queueUser->late_at = null;
        $queueUser->save();

        // Step 2: Use move() to shift them to the desired position,
        // which handles pushing other customers down correctly.
        $this->queueService->move($position, $queueUser->id);
    }

    public function cancelCustomer(QueueUser $queueUser){
        if (in_array($queueUser->status, ['served', 'cancelled'])) {
            throw new \Exception('Cannot cancel a customer who is already served or cancelled');
        }
        
        $user = Auth::user();
        if($queueUser->user_id !== $user->id && $user->role === 'customer'){
            throw new \Exception('You are not authorized to remove this customer from the queue');
        }
        
        $queueId = $queueUser->queue_id;
        $queueUser->update([
            'status' => 'cancelled',
            'position' => null
        ]);

        $this->queueService->normalizePositions($queueId);
    }
}