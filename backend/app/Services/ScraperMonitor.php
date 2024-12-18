<?php

namespace App\Services;

use App\Models\SearchResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScraperMonitor
{
    private const CACHE_KEY_PREFIX = 'scraper_monitor:';
    private const CIRCUIT_BREAKER_KEY = 'circuit_breaker_status';
    private const ERROR_THRESHOLD = 5;
    private const TIME_WINDOW = 300; // 5 minutes in seconds

    public function recordSuccess(): void
    {
        $this->incrementCounter('success');
        $this->resetFailureCount();
        
        if ($this->isCircuitOpen()) {
            $this->closeCircuit();
        }
    }

    public function recordFailure(string $reason): void
    {
        $this->incrementCounter('failure');
        $failureCount = $this->incrementFailureCount();
        
        Log::warning('Scraper failure recorded', [
            'reason' => $reason,
            'failure_count' => $failureCount
        ]);

        if ($failureCount >= self::ERROR_THRESHOLD) {
            $this->openCircuit();
        }
    }

    public function isCircuitOpen(): bool
    {
        return Cache::get(self::CACHE_KEY_PREFIX . self::CIRCUIT_BREAKER_KEY, false);
    }

    public function getStats(): array
    {
        // Get recent results (last 5 minutes)
        $recentResults = SearchResult::where('scraped_at', '>=', now()->subMinutes(5))
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();

        // Get total counts
        $totalStats = SearchResult::select(
            DB::raw('count(*) as total'),
            DB::raw('sum(case when status = \'success\' then 1 else 0 end) as success'),
            DB::raw('sum(case when status = \'failed\' then 1 else 0 end) as failed')
        )->first();

        // Get recent error messages
        $recentErrors = SearchResult::where('status', 'failed')
            ->where('scraped_at', '>=', now()->subHours(1))
            ->select('error_message', DB::raw('count(*) as count'))
            ->groupBy('error_message')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->pluck('count', 'error_message')
            ->toArray();

        return [
            'success_rate' => $this->calculateSuccessRate(),
            'circuit_breaker_status' => $this->isCircuitOpen() ? 'open' : 'closed',
            'recent_results' => $recentResults,
            'total_processed' => $totalStats->total ?? 0,
            'total_success' => $totalStats->success ?? 0,
            'total_failed' => $totalStats->failed ?? 0,
            'recent_errors' => $recentErrors
        ];
    }

    private function calculateSuccessRate(): float
    {
        $window = now()->subSeconds(self::TIME_WINDOW);
        
        $stats = SearchResult::where('scraped_at', '>=', $window)
            ->select(
                DB::raw('count(*) as total'),
                DB::raw('sum(case when status = \'success\' then 1 else 0 end) as success')
            )
            ->first();

        if (!$stats || $stats->total === 0) {
            return 100.0;
        }

        return round(($stats->success / $stats->total) * 100, 2);
    }

    private function incrementCounter(string $type): void
    {
        $key = self::CACHE_KEY_PREFIX . $type . '_count';
        Cache::increment($key);
    }

    private function incrementFailureCount(): int
    {
        $key = self::CACHE_KEY_PREFIX . 'consecutive_failures';
        return Cache::increment($key);
    }

    private function resetFailureCount(): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX . 'consecutive_failures');
    }

    private function openCircuit(): void
    {
        Cache::put(
            self::CACHE_KEY_PREFIX . self::CIRCUIT_BREAKER_KEY,
            true,
            now()->addMinutes(5)
        );
        
        Log::alert('Circuit breaker opened due to excessive failures');
    }

    private function closeCircuit(): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX . self::CIRCUIT_BREAKER_KEY);
        Log::info('Circuit breaker closed');
    }
}
