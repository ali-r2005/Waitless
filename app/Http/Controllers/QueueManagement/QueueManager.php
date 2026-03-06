<?php

namespace App\Http\Controllers\QueueManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Queue;
use App\Models\QueueUser;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Notifications\NewMessageNotification;
use App\Events\SendUpdate;
use App\Models\ServedCustomer;
use Carbon\Carbon;
use App\Services\QueueManagerService;
use App\Services\QueueService;
use Illuminate\Validation\ValidationException;

class QueueManager extends Controller
{
    public function __construct(private QueueManagerService $queueManagerService, private QueueService $queueService) {}

    public function addCustomerToQueue(Queue $queue, User $user){
        try {
           $this->queueManagerService->addcustumer($queue, $user);
            return response()->json([
                'status' => 'success',
                'message' => 'Customer added to queue successfully'
            ], Response::HTTP_OK);
        }
        catch (\Exception $e) {
            Log::error('Failed to add customer to queue: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add customer to queue'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

     public function removeCustomerFromQueue(Request $request){
        try {
            
        } catch (\Exception $e) {
            Log::error('Failed to remove customer from queue: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove customer from queue'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
