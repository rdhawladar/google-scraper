<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Models\SearchResult;
use App\Services\ScraperMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ScraperAnalyticsController extends Controller
{
    private $monitor;

    public function __construct(ScraperMonitor $monitor)
    {
        $this->monitor = $monitor;
    }

    public function getStats(): JsonResponse
    {
        $metrics = $this->monitor->getMetrics();
        
        $stats = [
            'performance' => [
                'success_rate' => $metrics['success_rate'],
                'total_requests' => $metrics['success_count'] + $metrics['failure_count'],
                'circuit_status' => $metrics['circuit_status'],
                'failure_reasons' => $metrics['failure_reasons']
            ],
            'keywords' => [
                'total' => Keyword::count(),
                'completed' => Keyword::where('status', 'completed')->count(),
                'failed' => Keyword::where('status', 'failed')->count(),
                'pending' => Keyword::where('status', 'pending')->count()
            ],
            'results' => [
                'total' => SearchResult::count(),
                'by_type' => SearchResult::select('type', DB::raw('count(*) as count'))
                    ->groupBy('type')
                    ->get()
                    ->pluck('count', 'type')
            ],
            'recent_failures' => Keyword::where('status', 'failed')
                ->latest()
                ->take(5)
                ->get(['id', 'keyword', 'updated_at'])
        ];

        // Calculate average results per keyword
        if ($stats['keywords']['completed'] > 0) {
            $stats['results']['avg_per_keyword'] = $stats['results']['total'] / $stats['keywords']['completed'];
        }

        return response()->json($stats);
    }

    public function getHourlyStats(): JsonResponse
    {
        $hourlyStats = SearchResult::select(
            DB::raw("to_char(created_at, 'YYYY-MM-DD HH24:00:00') as hour"),
            DB::raw('count(*) as count')
        )
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return response()->json($hourlyStats);
    }

    public function getFailureAnalysis(): JsonResponse
    {
        $failureStats = Keyword::where('status', 'failed')
            ->select(
                DB::raw("to_char(updated_at, 'YYYY-MM-DD') as date"),
                DB::raw('count(*) as count')
            )
            ->where('updated_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'daily_failures' => $failureStats,
            'failure_reasons' => $this->monitor->getMetrics()['failure_reasons']
        ]);
    }
}
