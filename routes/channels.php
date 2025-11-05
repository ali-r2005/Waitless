<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:api']]); // JWT authentication

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('update.{receiver_id}', function ($user, $receiver_id) {
    return true;
});

Broadcast::channel('staff.queue.{queue_id}', function ($user, $queue_id) {
    return true;
});