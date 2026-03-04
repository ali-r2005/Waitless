<?php

namespace App\Services;

class QueueService
{
     public function normalizePositions($queueId)
    {
        $customers = QueueUser::where('queue_id', $queueId)
            ->orderBy('position')
            ->get();

        foreach ($customers as $index => $customer) {
            $customer->position = $index + 1;
            $customer->save();
        }
    }
}
