<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ProxyManager
{
    private $proxies = [];
    private $lastUsedProxy = null;
    private const PROXY_HEALTH_CACHE_KEY = 'proxy_health_status';
    private const PROXY_HEALTH_TTL = 300; // 5 minutes

    public function __construct()
    {
        // Load proxies from environment variable
        $proxyList = env('PROXY_LIST', '');
        if (!empty($proxyList)) {
            $this->proxies = array_filter(explode(',', $proxyList));
        }
    }

    public function getNextProxy(): ?string
    {
        if (empty($this->proxies)) {
            return null;
        }

        $healthyProxies = $this->getHealthyProxies();
        if (empty($healthyProxies)) {
            return null;
        }

        // Avoid using the same proxy consecutively
        do {
            $proxy = $healthyProxies[array_rand($healthyProxies)];
        } while ($proxy === $this->lastUsedProxy && count($healthyProxies) > 1);

        $this->lastUsedProxy = $proxy;
        return $proxy;
    }

    private function getHealthyProxies(): array
    {
        $healthStatus = Cache::get(self::PROXY_HEALTH_CACHE_KEY, []);
        
        // If health status is expired, check all proxies
        if (empty($healthStatus)) {
            $healthStatus = $this->checkAllProxies();
            Cache::put(self::PROXY_HEALTH_CACHE_KEY, $healthStatus, self::PROXY_HEALTH_TTL);
        }

        return array_keys(array_filter($healthStatus));
    }

    private function checkAllProxies(): array
    {
        $healthStatus = [];
        foreach ($this->proxies as $proxy) {
            $healthStatus[$proxy] = $this->checkProxyHealth($proxy);
        }
        return $healthStatus;
    }

    private function checkProxyHealth(string $proxy): bool
    {
        try {
            $response = Http::timeout(5)
                ->withOptions([
                    'proxy' => $proxy,
                    'verify' => false // Disable SSL verification for proxies
                ])
                ->get('https://www.google.com/robots.txt');

            return $response->successful();
        } catch (\Exception $e) {
            Log::warning("Proxy health check failed for {$proxy}: " . $e->getMessage());
            return false;
        }
    }

    public function markProxyUnhealthy(string $proxy): void
    {
        $healthStatus = Cache::get(self::PROXY_HEALTH_CACHE_KEY, []);
        $healthStatus[$proxy] = false;
        Cache::put(self::PROXY_HEALTH_CACHE_KEY, $healthStatus, self::PROXY_HEALTH_TTL);
    }
}
