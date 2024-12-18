<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimiter
{
    private const RATE_LIMIT_PREFIX = 'rate_limit:';
    private const GLOBAL_RATE_KEY = 'global';
    private const DEFAULT_RATE_LIMIT = 60; // requests per minute
    private const DEFAULT_WINDOW = 60; // window size in seconds

    public function canProceed(string $key = self::GLOBAL_RATE_KEY): bool
    {
        $rateLimit = (int)env('SCRAPER_RATE_LIMIT', self::DEFAULT_RATE_LIMIT);
        $cacheKey = self::RATE_LIMIT_PREFIX . $key;
        
        // Get current window's request count
        $currentCount = Cache::get($cacheKey, 0);
        
        if ($currentCount >= $rateLimit) {
            Log::warning("Rate limit exceeded for key: {$key}");
            return false;
        }

        // Increment the counter
        Cache::increment($cacheKey);
        
        // Set expiry if this is the first request in the window
        if ($currentCount === 0) {
            Cache::put($cacheKey, 1, self::DEFAULT_WINDOW);
        }

        return true;
    }

    public function getRemainingLimit(string $key = self::GLOBAL_RATE_KEY): int
    {
        $rateLimit = (int)env('SCRAPER_RATE_LIMIT', self::DEFAULT_RATE_LIMIT);
        $currentCount = Cache::get(self::RATE_LIMIT_PREFIX . $key, 0);
        return max(0, $rateLimit - $currentCount);
    }

    public function trackFailure(string $key = self::GLOBAL_RATE_KEY): void
    {
        $failureKey = self::RATE_LIMIT_PREFIX . $key . ':failures';
        $failures = Cache::increment($failureKey);

        // If we see too many failures, reduce the rate limit temporarily
        if ($failures > 5) {
            $rateLimit = (int)env('SCRAPER_RATE_LIMIT', self::DEFAULT_RATE_LIMIT);
            Cache::put(self::RATE_LIMIT_PREFIX . $key, (int)($rateLimit * 0.8), self::DEFAULT_WINDOW);
            Log::warning("Reducing rate limit for {$key} due to multiple failures");
        }
    }
}
