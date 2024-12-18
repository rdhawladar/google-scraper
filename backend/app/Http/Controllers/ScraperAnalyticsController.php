<?php

namespace App\Http\Controllers;

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
        $stats = $this->monitor->getStats();
        
        return response()->json([
            'performance' => [
                'success_rate' => $stats['success_rate'],
                'circuit_breaker' => $stats['circuit_breaker_status'],
                'total_processed' => $stats['total_processed'],
                'total_success' => $stats['total_success'],
                'total_failed' => $stats['total_failed']
            ],
            'recent_activity' => $stats['recent_results']
        ]);
    }

    public function getHourlyStats(): JsonResponse
    {
        $hourlyStats = SearchResult::select(
            DB::raw('date_trunc(\'hour\', scraped_at) as hour'),
            DB::raw('status'),
            DB::raw('count(*) as count')
        )
        ->where('scraped_at', '>=', now()->subDay())
        ->groupBy('hour', 'status')
        ->orderBy('hour')
        ->get();

        $formattedStats = $hourlyStats->groupBy('hour')
            ->map(function ($group) {
                return [
                    'timestamp' => $group[0]->hour,
                    'success' => $group->where('status', 'success')->sum('count'),
                    'failed' => $group->where('status', 'failed')->sum('count'),
                    'total' => $group->sum('count')
                ];
            })
            ->values();

        return response()->json(['hourly_stats' => $formattedStats]);
    }

    public function getFailureAnalysis(): JsonResponse
    {
        // Get daily failure stats
        $failureStats = SearchResult::select(
            DB::raw('date_trunc(\'day\', scraped_at) as date'),
            DB::raw('count(*) as count')
        )
        ->where('status', 'failed')
        ->where('scraped_at', '>=', now()->subDays(7))
        ->groupBy('date')
        ->orderBy('date')
        ->get()
        ->map(function ($stat) {
            return [
                'date' => $stat->date,
                'count' => $stat->count
            ];
        });

        // Get recent failures with error messages
        $recentFailures = SearchResult::where('status', 'failed')
            ->where('scraped_at', '>=', now()->subHours(24))
            ->select('error_message', DB::raw('count(*) as count'))
            ->groupBy('error_message')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($failure) {
                return [
                    'reason' => $failure->error_message ?? 'Unknown error',
                    'count' => $failure->count
                ];
            });

        return response()->json([
            'daily_failures' => $failureStats,
            'recent_failures' => $recentFailures
        ]);
    }

    public function getKeywordStats(): JsonResponse
    {
        $stats = SearchResult::select(
            'keywords.keyword',
            DB::raw('count(*) as total_attempts'),
            DB::raw('sum(case when status = \'success\' then 1 else 0 end) as successful'),
            DB::raw('sum(case when status = \'failed\' then 1 else 0 end) as failed'),
            DB::raw('max(scraped_at) as last_attempt')
        )
        ->join('keywords', 'search_results.keyword_id', '=', 'keywords.id')
        ->groupBy('keywords.keyword')
        ->orderBy('total_attempts', 'desc')
        ->limit(10)
        ->get();

        return response()->json(['keyword_stats' => $stats]);
    }
}
