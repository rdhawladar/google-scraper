<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimiter
{
    private const CACHE_KEY_PREFIX = 'rate_limiter:';
    private const MAX_REQUESTS = 10; // Maximum requests per window
    private const TIME_WINDOW = 60; // Time window in seconds
    private const COOLDOWN_PERIOD = 30; // Cooldown period in seconds after hitting limit

    public function throttle(): void
    {
        $key = $this->getCacheKey();
        $requests = Cache::get($key, 0);

        if ($requests >= self::MAX_REQUESTS) {
            $this->enforceRateLimit();
        }

        Cache::increment($key);
        Cache::put($key, $requests + 1, now()->addSeconds(self::TIME_WINDOW));
    }

    public function canProceed(): bool
    {
        $key = $this->getCacheKey();
        $requests = Cache::get($key, 0);
        return $requests < self::MAX_REQUESTS;
    }

    public function getRemainingRequests(): int
    {
        $key = $this->getCacheKey();
        $requests = Cache::get($key, 0);
        return max(0, self::MAX_REQUESTS - $requests);
    }

    public function resetLimiter(): void
    {
        Cache::forget($this->getCacheKey());
    }

    private function enforceRateLimit(): void
    {
        Log::warning('Rate limit exceeded, enforcing cooldown period', [
            'max_requests' => self::MAX_REQUESTS,
            'time_window' => self::TIME_WINDOW,
            'cooldown' => self::COOLDOWN_PERIOD
        ]);

        throw new \Exception(sprintf(
            'Rate limit exceeded. Maximum %d requests allowed per %d seconds.',
            self::MAX_REQUESTS,
            self::TIME_WINDOW
        ));
    }

    private function getCacheKey(): string
    {
        return self::CACHE_KEY_PREFIX . date('Y-m-d-H');
    }
}
