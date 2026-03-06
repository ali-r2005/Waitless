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
    
}