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

    public function addCustomerToQueue(Queue $queue, User $user)
    {
        try {
            $this->queueManagerService->addcustumer($queue, $user);
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

    public function removeCustomerFromQueue(Queue $queue, User $user)
    {
        try {
            $this->queueManagerService->removecustumer($queue, $user);
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

    public function getQueueCustomers(Request $request, Queue $queue)
    {
        try {
            $query = $queue->users();

            if ($request->query('status') === 'late') {
                $query->where('status', 'late');
            } else {
                $query->where(function ($q) {
                    $q->where('status', 'waiting')->orWhere('status', 'serving');
                });
            }

            $customers = $query->orderBy('position')->get();

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

    public function activateQueue(Queue $queue){
        try {
            $this->queueManagerService->activateQueue($queue);
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

    public function callNextCustomer(Queue $queue){
        try {
            $this->queueManagerService->callNextCustomer($queue);
            return response()->json([
                'status' => 'success',
                'message' => 'Next customer called successfully',
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to call next customer: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to call next customer'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function completeServing(Queue $queue){
        try {
            $this->queueManagerService->completeServing($queue);
            return response()->json([
                'status' => 'success',
                'message' => 'Serving completed successfully',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Failed to complete serving: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to complete serving'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function markCustomerAsLate(Queue $queue, User $user)
    {
        try {
            $this->queueManagerService->markCustomerAsLate($queue, $user);
            return response()->json([
                'status' => 'success',
                'message' => 'Customer marked as late successfully'
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            Log::error('Failed to mark customer as late: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark customer as late'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
