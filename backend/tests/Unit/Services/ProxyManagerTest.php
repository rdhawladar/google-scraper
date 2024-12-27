<?php

namespace Tests\Unit\Services;

use App\Services\ProxyManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProxyManagerTest extends TestCase
{
    private $proxyManager;
    private const TEST_PROXIES = ['http://proxy1:8080', 'http://proxy2:8080', 'http://proxy3:8080'];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the env function to return our test proxies
        $this->app->bind('env', function () {
            return function ($key, $default = null) {
                if ($key === 'PROXY_LIST') {
                    return implode(',', self::TEST_PROXIES);
                }
                return $default;
            };
        });

        $this->proxyManager = new ProxyManager();
        
        // Clear the cache before each test
        Cache::forget('proxy_health_status');
    }

    public function testConstructorLoadsProxiesFromEnvironment()
    {
        // Create a new instance to test the constructor
        $proxyManager = new ProxyManager();
        
        // Get next proxy to verify proxies were loaded
        Http::fake([
            'https://www.google.com/robots.txt' => Http::response('OK', 200),
        ]);
        
        $proxy = $proxyManager->getNextProxy();
        $this->assertNotNull($proxy);
        $this->assertIsString($proxy);
        $this->assertMatchesRegularExpression('/^https?:\/\/[^:]+:\d+$/', $proxy);
    }

    public function testGetNextProxyReturnsNullWhenNoProxiesAvailable()
    {
        // Mock the env to return empty proxy list
        $this->app->bind('env', function () {
            return function ($key, $default = null) {
                return '';
            };
        });

        $proxyManager = new ProxyManager();
        $this->assertNull($proxyManager->getNextProxy());
    }

    public function testGetNextProxyReturnsHealthyProxy()
    {
        Http::fake([
            'https://www.google.com/robots.txt' => Http::response('OK', 200),
        ]);

        $proxy = $this->proxyManager->getNextProxy();
        $this->assertNotNull($proxy);
        $this->assertIsString($proxy);
        $this->assertMatchesRegularExpression('/^https?:\/\/[^:]+:\d+$/', $proxy);
    }

    public function testGetNextProxyReturnsNullWhenAllProxiesUnhealthy()
    {
        Http::fake([
            'https://www.google.com/robots.txt' => Http::response('Error', 500),
        ]);

        $proxy = $this->proxyManager->getNextProxy();
        $this->assertNull($proxy);
    }

    public function testMarkProxyUnhealthy()
    {
        // First make all proxies healthy
        Http::fake([
            'https://www.google.com/robots.txt' => Http::response('OK', 200),
        ]);

        // Get a healthy proxy
        $proxy = $this->proxyManager->getNextProxy();
        $this->assertNotNull($proxy);

        // Mark it as unhealthy
        $this->proxyManager->markProxyUnhealthy($proxy);

        // Verify the proxy health status in cache
        $healthStatus = Cache::get('proxy_health_status');
        $this->assertFalse($healthStatus[$proxy]);
    }

    public function testAvoidConsecutiveUseOfSameProxy()
    {
        Http::fake([
            'https://www.google.com/robots.txt' => Http::response('OK', 200),
        ]);

        $firstProxy = $this->proxyManager->getNextProxy();
        $secondProxy = $this->proxyManager->getNextProxy();

        // With more than one healthy proxy available, they should be different
        if (count(self::TEST_PROXIES) > 1) {
            $this->assertNotEquals($firstProxy, $secondProxy);
        }
    }
}
