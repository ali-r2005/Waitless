<?php

namespace App\Http\Controllers\QueueManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\QueueUser;

class CustomerController extends Controller
{
    public function getQueues(Request $request)
    {
        try {
            $user = $request->user();
            // Fetch queues the user is in, including pivot data (status, ticket_number, etc.)
            $queues = $user->queues()
                ->select('queues.id', 'name', 'scheduled_date', 'is_active', 'is_paused', 'start_time', 'preferences')
                ->wherePivotIn('status', ['waiting', 'serving', 'late'])
                ->get();

            return response()->json(['status' => 'success', 'data' => $queues], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getQueueCustomer(QueueUser $queueUser){
        try {
            $user = Auth::user();
            if($queueUser->user_id !== $user->id && $user->role === 'customer'){
                throw new \Exception('You are not authorized to get this customer from the queue');
            }
            $queueUser->load('queue');
            return response()->json(['status' => 'success', 'data' => $queueUser], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
