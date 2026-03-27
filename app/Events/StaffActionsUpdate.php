<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StaffActionsUpdate implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $action;
    public $message;
    private $user_id;
    private $queue_id;


    /**
     * Create a new event instance.
     */
    public function __construct($user_id, $action, $message, $queue_id)
    {
        $this->user_id = $user_id;
        $this->action = $action;
        $this->message = $message;
        $this->queue_id = $queue_id;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('staff.' . $this->user_id . '.actions.' . $this->queue_id),
        ];
    }
}
