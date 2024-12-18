<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScraperMonitor
{
    private const METRICS_PREFIX = 'scraper_metrics:';
    private const CIRCUIT_PREFIX = 'scraper_circuit:';
    private const SUCCESS_THRESHOLD = 0.7; // 70% success rate required
    private const FAILURE_THRESHOLD = 5; // Number of failures before circuit opens
    private const CIRCUIT_TIMEOUT = 300; // 5 minutes timeout for open circuit

    public function recordSuccess(string $type = 'default'): void
    {
        $this->incrementMetric($type . ':success');
        $this->updateSuccessRate($type);
    }

    public function recordFailure(string $type = 'default', string $reason = null): void
    {
        $this->incrementMetric($type . ':failure');
        $this->incrementMetric($type . ':failure:' . ($reason ?? 'unknown'));
        $this->updateSuccessRate($type);
        
        // Check if we need to open the circuit
        $failures = $this->getMetric($type . ':failure', 0);
        if ($failures >= self::FAILURE_THRESHOLD) {
            $this->openCircuit($type);
        }
    }

    public function canProceed(string $type = 'default'): bool
    {
        // Check if circuit is open
        $circuitKey = self::CIRCUIT_PREFIX . $type;
        $circuitStatus = Cache::get($circuitKey);
        
        if (!$circuitStatus) {
            return true;
        }

        // If circuit was opened more than timeout ago, allow a test request
        if (time() - $circuitStatus['opened_at'] > self::CIRCUIT_TIMEOUT) {
            Cache::put($circuitKey, [
                'status' => 'half_open',
                'opened_at' => $circuitStatus['opened_at']
            ], 3600);
            return true;
        }

        return false;
    }

    public function getMetrics(string $type = 'default'): array
    {
        return [
            'success_count' => $this->getMetric($type . ':success', 0),
            'failure_count' => $this->getMetric($type . ':failure', 0),
            'success_rate' => $this->getSuccessRate($type),
            'circuit_status' => $this->getCircuitStatus($type),
            'failure_reasons' => $this->getFailureReasons($type)
        ];
    }

    private function incrementMetric(string $key): void
    {
        $fullKey = self::METRICS_PREFIX . $key;
        Cache::increment($fullKey);
        
        // Set expiry if this is the first increment
        if (Cache::get($fullKey) === 1) {
            Cache::put($fullKey, 1, now()->addDay());
        }
    }

    private function getMetric(string $key, $default = null)
    {
        return Cache::get(self::METRICS_PREFIX . $key, $default);
    }

    private function updateSuccessRate(string $type): void
    {
        $success = $this->getMetric($type . ':success', 0);
        $failure = $this->getMetric($type . ':failure', 0);
        $total = $success + $failure;

        if ($total > 0) {
            $rate = $success / $total;
            Cache::put(self::METRICS_PREFIX . $type . ':rate', $rate, now()->addDay());

            // If success rate improves above threshold, close the circuit
            if ($rate >= self::SUCCESS_THRESHOLD) {
                $this->closeCircuit($type);
            }
        }
    }

    private function getSuccessRate(string $type): float
    {
        return Cache::get(self::METRICS_PREFIX . $type . ':rate', 1.0);
    }

    private function openCircuit(string $type): void
    {
        Log::warning("Opening circuit breaker for scraper type: {$type}");
        Cache::put(self::CIRCUIT_PREFIX . $type, [
            'status' => 'open',
            'opened_at' => time()
        ], now()->addHour());
    }

    private function closeCircuit(string $type): void
    {
        Log::info("Closing circuit breaker for scraper type: {$type}");
        Cache::forget(self::CIRCUIT_PREFIX . $type);
    }

    private function getCircuitStatus(string $type): string
    {
        $status = Cache::get(self::CIRCUIT_PREFIX . $type);
        return $status ? $status['status'] : 'closed';
    }

    private function getFailureReasons(string $type): array
    {
        $reasons = [];
        $pattern = self::METRICS_PREFIX . $type . ':failure:*';
        $keys = Cache::get($pattern, []);
        
        foreach ($keys as $key => $count) {
            $reason = str_replace(self::METRICS_PREFIX . $type . ':failure:', '', $key);
            $reasons[$reason] = $count;
        }

        return $reasons;
    }
}
