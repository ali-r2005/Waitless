<?php

namespace App\Services;

use App\Models\Queue;
use App\Models\QueueUser;
use App\Models\User;
use App\Events\SendActions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Events\StaffActionsUpdate;

class QueueManagerService
{
    public function __construct(private QueueService $queueService) {}
    public function addcustumer(Queue $queue, User $user){
         // Check if user is already waiting or being served in this specific queue
         $existingQueueUser = QueueUser::where('queue_id', $queue->id)
             ->where('user_id', $user->id)
             ->whereIn('status', ['waiting', 'serving'])
             ->first();

         if ($existingQueueUser) {
             throw new \Exception('You are already in this queue.');
         }

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
        
        $this->queueService->broadcastQueueUpdates($queue->id);
        event(new SendActions($user->id, 'added', 'You have been added to the queue ' . $queue->name . ' with ticket number ' . $ticket_number));
    }
    public function removecustumer(QueueUser $queueUser){
        $user = Auth::user();
        if($queueUser->user_id !== $user->id && $user->role === 'customer'){
            throw new \Exception('You are not authorized to remove this customer from the queue');
        }
        $queueId = $queueUser->queue_id;
        $queueUser->delete();
        if($user->role === 'customer'){
            event(new StaffActionsUpdate($queueUser->queue->user_id, 'removed', 'The customer ' . $queueUser->user->name . ' has remove himself     from the queue ' . $queueUser->queue->name, $queueId));
        }else{
            event(new SendActions($queueUser->user_id, 'removed', 'You have been removed from the queue ' . $queueUser->queue->name));
        }
        $this->queueService->normalizePositions($queueId);
        $this->queueService->broadcastQueueUpdates($queueId);
    }
    
    public function activateQueue(Queue $queue){
        $this->queueService->checker($queue, false, true, 'Cannot activate queue: The queue is already active or paused.');
        $customerCount = QueueUser::where('queue_id', $queue->id)
            ->where('status', 'waiting')
            ->count();

        if ($customerCount === 0) {
            throw new \Exception('Cannot activate queue: No customers currently waiting.');
        }

        $queue->is_active = true;
        $queue->is_paused = false;
        $queue->start_time = now();
        $queue->save();
        //send message to each customer in the queue 
        $this->queueService->normalizePositions($queue->id);
        $this->queueService->broadcastQueueUpdates($queue->id);
        //send message to the customer num 1 that his turn is now and for 2 that his turn is close
    }

    public function callNextCustomer(Queue $queue){
        $this->queueService->checker($queue, true, false, 'Cannot call next customer: The queue is not active or paused.');
        $nextQueueUser = QueueUser::where('queue_id', $queue->id)
            ->where('status', 'waiting')
            ->orderBy('position')
            ->first();

        if (!$nextQueueUser) {
            throw new \Exception('No waiting customers in this queue');
        }

        Log::info('Next customer queue_user id: ' . $nextQueueUser->id . ', user_id: ' . $nextQueueUser->user_id);

        // Update the exact queue_user row by its own primary key
        $nextQueueUser->update([
            'status' => 'serving',
            'start_serving_at' => now()
        ]);
        event(new SendActions($nextQueueUser->user_id, 'call', 'Your turn is now in the queue to be served in ' . $queue->name));

        $this->queueService->broadcastQueueUpdates($queue->id);
    }

    public function completeServing(Queue $queue){
        // Same reasoning: use the queue_user.id directly to update the exact pivot row
        $this->queueService->checker($queue, true, false, 'Cannot complete serving: The queue is not active or paused.');
        $servingQueueUser = QueueUser::where('queue_id', $queue->id)
            ->where('status', 'serving')
            ->first();

        if (!$servingQueueUser) {
            throw new \Exception('No customer is currently being served');
        }

        $servingQueueUser->update([
            'status' => 'served',
            'served_at' => now()
        ]);

        $this->queueService->normalizePositions($queue->id);
        $this->queueService->broadcastQueueUpdates($queue->id);
        event(new SendActions($servingQueueUser->user_id, 'served', 'You have been served in the queue ' . $queue->name));
    }

    public function markCustomerAsLate(QueueUser $queueUser){
        $this->queueService->checker($queueUser->queue, true, false, 'Cannot mark customer as late: The queue is not active.');
        $queueId = $queueUser->queue_id;
        $queueUser->update([
            'status' => 'late',
            'late_at' => now(),
            'position' => null
        ]);

        // Normalize positions since the user is no longer 'waiting'
        $this->queueService->normalizePositions($queueId);
        $this->queueService->broadcastQueueUpdates($queueId);
    }

    public function reinsertCustomer(QueueUser $queueUser, $position){
        $this->queueService->checker($queueUser->queue, true, false, 'Cannot reinsert customer: The queue is not active.');
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
        $this->queueService->broadcastQueueUpdates($queueId);
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
        $this->queueService->broadcastQueueUpdates($queueId);
    }

    public function deactivateQueue(Queue $queue){
        $this->queueService->checker($queue, true, null, 'Cannot deactivate queue: The queue is not active.');
        $activeCount = QueueUser::where('queue_id', $queue->id)
            ->whereIn('status', ['waiting', 'serving'])
            ->count();

        if ($activeCount > 0) {
            throw new \Exception('Cannot deactivate queue: There are still customers waiting or being served.');
        }

        // Remove late customers
        QueueUser::where('queue_id', $queue->id)
            ->where('status', 'late')
            ->delete();

        $queue->is_active = false;
        $queue->is_paused = false;
        $queue->start_time = null;
        $queue->save();

        $this->queueService->broadcastQueueUpdates($queue->id);
    }

    public function pauseQueue(Queue $queue){
        $this->queueService->checker($queue, true, false, 'Cannot pause queue: The queue is not in a state that allows it to be paused.');
        
        $servingCustomerExists = QueueUser::where('queue_id', $queue->id)
            ->where('status', 'serving')
            ->exists();

        if ($servingCustomerExists) {
            throw new \Exception('Cannot pause queue: A customer is currently being served.');
        }

        $queue->is_paused = true;
        $queue->save();
        $this->queueService->broadcastQueueUpdates($queue->id);
    }

    public function resumeQueue(Queue $queue){
        $this->queueService->checker($queue, true, true, 'Cannot resume queue: The queue is not in a state that allows it to be resumed.');
        $queue->is_paused = false;
        $queue->save();
        $this->queueService->broadcastQueueUpdates($queue->id);
    }
}