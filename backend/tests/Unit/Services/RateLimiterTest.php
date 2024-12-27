<?php

namespace Tests\Unit\Services;

use App\Services\RateLimiter;
use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimiterTest extends TestCase
{
    private RateLimiter $rateLimiter;
    private string $testCacheKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rateLimiter = new RateLimiter();
        $this->testCacheKey = 'rate_limiter:' . date('Y-m-d-H');
        
        // Mock the Log facade
        Log::shouldReceive('warning')->andReturn(null);
    }

    public function testThrottleIncrementsRequestCount()
    {
        Cache::shouldReceive('get')
            ->once()
            ->with($this->testCacheKey, 0)
            ->andReturn(5);

        Cache::shouldReceive('increment')
            ->once()
            ->with($this->testCacheKey)
            ->andReturn(6);

        Cache::shouldReceive('put')
            ->once()
            ->with($this->testCacheKey, 6, 60)
            ->andReturn(true);

        $this->rateLimiter->throttle();
    }

    public function testThrottleThrowsExceptionWhenLimitExceeded()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        Cache::shouldReceive('get')
            ->once()
            ->with($this->testCacheKey, 0)
            ->andReturn(10); // MAX_REQUESTS reached

        $this->rateLimiter->throttle();
    }

    public function testCanProceedReturnsTrueWhenUnderLimit()
    {
        Cache::shouldReceive('get')
            ->once()
            ->with($this->testCacheKey, 0)
            ->andReturn(5);

        $this->assertTrue($this->rateLimiter->canProceed());
    }

    public function testCanProceedReturnsFalseWhenAtLimit()
    {
        Cache::shouldReceive('get')
            ->once()
            ->with($this->testCacheKey, 0)
            ->andReturn(10); // MAX_REQUESTS reached

        $this->assertFalse($this->rateLimiter->canProceed());
    }

    public function testGetRemainingRequestsReturnsCorrectCount()
    {
        Cache::shouldReceive('get')
            ->once()
            ->with($this->testCacheKey, 0)
            ->andReturn(4);

        $this->assertEquals(6, $this->rateLimiter->getRemainingRequests()); // 10 - 4 = 6
    }

    public function testGetRemainingRequestsReturnsZeroWhenOverLimit()
    {
        Cache::shouldReceive('get')
            ->once()
            ->with($this->testCacheKey, 0)
            ->andReturn(15); // Over MAX_REQUESTS

        $this->assertEquals(0, $this->rateLimiter->getRemainingRequests());
    }

    public function testResetLimiterClearsCache()
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with($this->testCacheKey)
            ->andReturn(true);

        $this->rateLimiter->resetLimiter();
    }
}
