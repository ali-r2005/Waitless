<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Queue;
use App\Models\QueueUser;
use App\Models\User;
use App\Models\ServedCustomer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function ownerOverview(): JsonResponse
    {
        $user = auth()->user();
        $businessId = $user->business_id;

        if (!$businessId) {
            return response()->json(['message' => 'No business associated with this account.'], 404);
        }

        $today = now()->startOfDay();
        $now = now();

        // ── KPIs ──────────────────────────────────────────────────────

        $totalCustomersToday = QueueUser::whereHas('queue', fn($q) => $q->where('business_id', $businessId))
            ->where('status', 'served')
            ->where('served_at', '>=', $today)
            ->count();

        $avgWaitTime = QueueUser::whereHas('queue', fn($q) => $q->where('business_id', $businessId))
            ->where('status', 'served')
            ->where('served_at', '>=', $today)
            ->whereNotNull('start_serving_at')
            ->selectRaw('COALESCE(AVG(TIMESTAMPDIFF(SECOND, start_serving_at, served_at)), 0) as avg_seconds')
            ->value('avg_seconds');

        $avgWaitTimeMinutes = $avgWaitTime ? round($avgWaitTime / 60, 1) : 0;

        $todayQueueUsers = QueueUser::whereHas('queue', fn($q) => $q->where('business_id', $businessId))
            ->where(function ($q) use ($today) {
                $q->where('served_at', '>=', $today)
                  ->orWhere('status', 'waiting')
                  ->orWhere(fn($q) => $q->where('status', 'late')->where('late_at', '>=', $today))
                  ->orWhere(fn($q) => $q->where('status', 'cancelled')->where('updated_at', '>=', $today));
            });

        $totalToday = (clone $todayQueueUsers)->count();
        $servedToday = (clone $todayQueueUsers)->where('status', 'served')->count();
        $servedRate = $totalToday > 0 ? round(($servedToday / $totalToday) * 100, 1) : 0;

        $activeQueues = Queue::where('business_id', $businessId)->where('is_active', true)->count();
        $totalQueues = Queue::where('business_id', $businessId)->count();
        $staffOnDuty = User::where('business_id', $businessId)->where('role', 'staff')->count();

        $peakHour = QueueUser::whereHas('queue', fn($q) => $q->where('business_id', $businessId))
            ->where('created_at', '>=', $today)
            ->selectRaw("DATE_FORMAT(created_at, '%H:00') as hour, COUNT(*) as count")
            ->groupBy('hour')
            ->orderByDesc('count')
            ->first();

        // ── Customer Volume – This Week ────────────────────────────────

        $weekStart = now()->startOfWeek();
        $customerVolumeWeek = QueueUser::whereHas('queue', fn($q) => $q->where('business_id', $businessId))
            ->where('created_at', '>=', $weekStart)
            ->selectRaw("DATE_FORMAT(created_at, '%a') as day")
            ->selectRaw("COUNT(*) as customers")
            ->selectRaw("SUM(CASE WHEN status = 'served' THEN 1 ELSE 0 END) as served")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%a')"))
            ->orderBy(DB::raw("MIN(created_at)"))
            ->get();

        // ── Hourly Traffic – Today ──────────────────────────────────────

        $hourlyTraffic = QueueUser::whereHas('queue', fn($q) => $q->where('business_id', $businessId))
            ->where('created_at', '>=', $today)
            ->selectRaw("DATE_FORMAT(created_at, '%l%p') as hour")
            ->selectRaw("COUNT(*) as customers")
            ->groupBy('hour')
            ->orderBy(DB::raw("MIN(created_at)"))
            ->get();

        // ── Queue Performance ───────────────────────────────────────────

        $queuePerformance = Queue::where('business_id', $businessId)
            ->withCount(['users as served' => fn($q) => $q->where('queue_user.status', 'served')])
            ->withCount(['users as waiting' => fn($q) => $q->where('queue_user.status', 'waiting')])
            ->withCount(['users as late' => fn($q) => $q->where('queue_user.status', 'late')])
            ->withCount(['users as cancelled' => fn($q) => $q->where('queue_user.status', 'cancelled')])
            ->get()
            ->map(fn($q) => [
                'name' => $q->name,
                'avgWait' => round($q->average_waiting_time / 60, 1),
                'served' => $q->served,
                'waiting' => $q->waiting,
                'late' => $q->late,
                'cancelled' => $q->cancelled,
            ]);

        // ── Staff Performance ───────────────────────────────────────────

        $staffPerformance = User::where('business_id', $businessId)
            ->where('role', 'staff')
            ->withCount(['servedCustomers' => fn($q) => $q->where('created_at', '>=', $today)])
            ->get()
            ->map(function ($staff) {
                $avgSeconds = QueueUser::where('staff_id', $staff->id)
                    ->where('status', 'served')
                    ->where('served_at', '>=', now()->startOfDay())
                    ->whereNotNull('start_serving_at')
                    ->selectRaw('COALESCE(AVG(TIMESTAMPDIFF(SECOND, start_serving_at, served_at)), 0) as avg')
                    ->value('avg');

                return [
                    'name' => $staff->name,
                    'served' => $staff->servedCustomers,
                    'avgTime' => round($avgSeconds / 60, 1),
                    'rating' => 0,
                ];
            });

        // ── Customer Status Breakdown ───────────────────────────────────

        $customerStatusBreakdown = QueueUser::whereHas('queue', fn($q) => $q->where('business_id', $businessId))
            ->where(function ($q) use ($today) {
                $q->where('served_at', '>=', $today)
                  ->orWhere('status', 'waiting')
                  ->orWhere(fn($q) => $q->where('status', 'late'))
                  ->orWhere(fn($q) => $q->where('status', 'cancelled')->where('updated_at', '>=', $today));
            })
            ->selectRaw("CASE WHEN status = 'served' THEN 'Served' WHEN status = 'waiting' THEN 'Waiting' WHEN status = 'late' THEN 'Late' WHEN status = 'cancelled' THEN 'Cancelled' ELSE status END as status")
            ->selectRaw("COUNT(*) as count")
            ->groupBy('status')
            ->get()
            ->map(fn($item, $i) => [
                'status' => $item->status,
                'count' => $item->count,
                'fill' => match ($i) { 0 => 'var(--color-chart-1)', 1 => 'var(--color-chart-2)', 2 => 'var(--color-chart-5)', 3 => 'var(--color-chart-3)', default => 'var(--color-chart-4)' },
            ]);

        // ── Top Queues ──────────────────────────────────────────────────

        $topQueues = Queue::where('business_id', $businessId)
            ->withCount(['users as customer_count' => fn($q) => $q->whereIn('queue_user.status', ['waiting', 'serving'])])
            ->get()
            ->map(fn($q) => [
                'name' => $q->name,
                'customers' => $q->customer_count,
                'waitTime' => round($q->average_waiting_time / 60, 1),
                'status' => $q->is_paused ? 'paused' : ($q->is_active ? 'active' : 'inactive'),
            ]);

        // ── Build Response ──────────────────────────────────────────────

        return response()->json([
            'stats' => [
                'totalCustomersToday' => $totalCustomersToday,
                'totalCustomersChange' => 0,
                'avgWaitTime' => $avgWaitTimeMinutes,
                'avgWaitTimeChange' => 0,
                'servedRate' => $servedRate,
                'servedRateChange' => 0,
                'activeQueues' => $activeQueues,
                'totalQueues' => $totalQueues,
                'peakHour' => $peakHour ? $peakHour->hour : '—',
                'staffOnDuty' => $staffOnDuty,
            ],
            'customerVolumeWeek' => $customerVolumeWeek,
            'hourlyTraffic' => $hourlyTraffic,
            'queuePerformance' => $queuePerformance,
            'staffPerformance' => $staffPerformance,
            'customerStatusBreakdown' => $customerStatusBreakdown,
            'topQueues' => $topQueues,
        ]);
    }

    public function staffOverview(): JsonResponse
    {
        $staff = auth()->user();
        $today = now()->startOfDay();

        // Queues this staff member owns
        $queueIds = Queue::where('user_id', $staff->id)->pluck('id');

        // ── KPIs ──────────────────────────────────────────────────────

        $servedToday = QueueUser::whereIn('queue_id', $queueIds)
            ->where('status', 'served')
            ->where('served_at', '>=', $today)
            ->count();

        $currentWaiting = QueueUser::whereIn('queue_id', $queueIds)
            ->where('status', 'waiting')
            ->count();

        $avgServiceSeconds = QueueUser::whereIn('queue_id', $queueIds)
            ->where('status', 'served')
            ->where('served_at', '>=', $today)
            ->whereNotNull('start_serving_at')
            ->selectRaw('COALESCE(AVG(TIMESTAMPDIFF(SECOND, start_serving_at, served_at)), 0) as avg')
            ->value('avg');

        $avgServiceMinutes = $avgServiceSeconds ? round($avgServiceSeconds / 60, 1) : 0;

        $lateCustomers = QueueUser::whereIn('queue_id', $queueIds)
            ->where('status', 'late')
            ->count();

        $myQueuesActive = Queue::where('user_id', $staff->id)
            ->where('is_active', true)
            ->count();

        // ── Hourly Served – Today ──────────────────────────────────────

        $myServedToday = QueueUser::whereIn('queue_id', $queueIds)
            ->where('status', 'served')
            ->where('served_at', '>=', $today)
            ->selectRaw("DATE_FORMAT(served_at, '%l%p') as hour")
            ->selectRaw("COUNT(*) as served")
            ->groupBy('hour')
            ->orderBy(DB::raw("MIN(served_at)"))
            ->get();

        // ── Weekly Served ──────────────────────────────────────────────

        $weekStart = now()->startOfWeek();
        $servedThisWeek = QueueUser::whereIn('queue_id', $queueIds)
            ->where('status', 'served')
            ->where('served_at', '>=', $weekStart)
            ->selectRaw("DATE_FORMAT(served_at, '%a') as day")
            ->selectRaw("COUNT(*) as served")
            ->groupBy(DB::raw("DATE_FORMAT(served_at, '%a')"))
            ->orderBy(DB::raw("MIN(served_at)"))
            ->get();

        // ── Current Queue ──────────────────────────────────────────────

        $currentQueue = Queue::where('user_id', $staff->id)
            ->where('is_active', true)
            ->first();

        $currentQueueData = null;
        if ($currentQueue) {
            $waiting = QueueUser::where('queue_id', $currentQueue->id)->where('status', 'waiting')->count();
            $serving = QueueUser::where('queue_id', $currentQueue->id)->where('status', 'serving')->first();

            $currentQueueData = [
                'id' => $currentQueue->id,
                'name' => $currentQueue->name,
                'waiting' => $waiting,
                'serving' => $serving ? $serving->user->name . ' (#' . $serving->ticket_number . ')' : null,
                'avgWait' => round($currentQueue->average_waiting_time / 60, 1),
                'status' => $currentQueue->is_paused ? 'paused' : 'active',
            ];
        }

        // ── Recent Activity ─────────────────────────────────────────────

        $recentActivity = QueueUser::whereIn('queue_id', $queueIds)
            ->whereIn('status', ['served', 'cancelled', 'late'])
            ->whereNotNull('served_at')
            ->orderByDesc('served_at')
            ->take(7)
            ->get()
            ->map(fn($qu) => [
                'time' => $qu->served_at ? $qu->served_at->format('g:i A') : '',
                'customer' => $qu->user->name,
                'ticket' => $qu->ticket_number,
                'action' => $qu->status === 'served' ? 'served' : $qu->status,
            ]);

        // Also get recently "called" customers
        $recentCalled = QueueUser::whereIn('queue_id', $queueIds)
            ->where('status', 'serving')
            ->orderByDesc('start_serving_at')
            ->take(3)
            ->get()
            ->map(fn($qu) => [
                'time' => $qu->start_serving_at ? $qu->start_serving_at->format('g:i A') : '',
                'customer' => $qu->user->name,
                'ticket' => $qu->ticket_number,
                'action' => 'called',
            ]);

        $recentActivity = $recentCalled->concat($recentActivity)->sortByDesc('time')->take(7)->values();

        // ── Customer Status Breakdown ───────────────────────────────────

        $customerStatusBreakdown = QueueUser::whereIn('queue_id', $queueIds)
            ->where('created_at', '>=', $today)
            ->selectRaw("CASE WHEN status = 'served' THEN 'Served' WHEN status = 'waiting' THEN 'Waiting' WHEN status = 'late' THEN 'Late' WHEN status = 'cancelled' THEN 'Cancelled' ELSE status END as status")
            ->selectRaw("COUNT(*) as count")
            ->groupBy('status')
            ->get()
            ->map(fn($item, $i) => [
                'status' => $item->status,
                'count' => $item->count,
                'fill' => match ($i) { 0 => 'var(--color-chart-1)', 1 => 'var(--color-chart-2)', 2 => 'var(--color-chart-5)', 3 => 'var(--color-chart-3)', default => 'var(--color-chart-4)' },
            ]);

        // ── Build Response ──────────────────────────────────────────────

        return response()->json([
            'stats' => [
                'servedToday' => $servedToday,
                'servedChange' => 0,
                'currentWaiting' => $currentWaiting,
                'avgServiceTime' => $avgServiceMinutes,
                'avgServiceChange' => 0,
                'lateCustomers' => $lateCustomers,
                'myQueuesActive' => $myQueuesActive,
            ],
            'myServedToday' => $myServedToday,
            'servedThisWeek' => $servedThisWeek,
            'currentQueue' => $currentQueueData,
            'recentActivity' => $recentActivity,
            'customerStatusBreakdown' => $customerStatusBreakdown,
        ]);
    }
}
