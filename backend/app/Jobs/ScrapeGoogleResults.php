<?php

namespace App\Jobs;

use App\Models\Keyword;
use App\Services\ProxyManager;
use App\Services\RateLimiter;
use App\Services\GoogleResultParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ScrapeGoogleResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $keyword;
    protected $timeout = 30;
    protected $proxyManager;
    protected $rateLimiter;
    protected $resultParser;

    public function __construct(Keyword $keyword)
    {
        $this->keyword = $keyword;
        $this->proxyManager = new ProxyManager();
        $this->rateLimiter = new RateLimiter();
        $this->resultParser = new GoogleResultParser();
    }

    public function handle()
    {
        try {
            $this->keyword->update(['status' => 'processing']);
            
            $results = $this->scrapeGoogle($this->keyword->keyword);
            
            $this->keyword->update([
                'status' => 'completed',
            ]);
        } catch (\Exception $e) {
            Log::error('Error scraping Google results: ' . $e->getMessage(), [
                'keyword_id' => $this->keyword->id,
                'keyword' => $this->keyword->keyword
            ]);
            
            $this->keyword->update([
                'status' => 'failed',
                'results' => ['error' => $e->getMessage()]
            ]);
        }
    }

    protected function scrapeGoogle($query)
    {
        $mobileUserAgents = [
            // Latest iOS Safari
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
            // Latest Chrome on Android
            'Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.144 Mobile Safari/537.36',
            // Latest Firefox on Android
            'Mozilla/5.0 (Android 14; Mobile; rv:121.0) Gecko/121.0 Firefox/121.0',
            // Latest Edge on iOS
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 EdgiOS/120.0.2210.126 Mobile/15E148 Safari/605.1.15',
            // Latest Samsung Browser
            'Mozilla/5.0 (Linux; Android 14; SAMSUNG SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/23.0 Chrome/115.0.0.0 Mobile Safari/537.36'
        ];

        $results = [];
        $retries = 0;
        $maxRetries = 3;
        $lastUsedAgent = null;

        while ($retries < $maxRetries) {
            try {
                // Check rate limits before proceeding
                if (!$this->rateLimiter->canProceed()) {
                    Log::warning('Rate limit exceeded, delaying job', [
                        'keyword_id' => $this->keyword->id,
                        'remaining_attempts' => $maxRetries - $retries
                    ]);
                    
                    // Release the job back to the queue with a delay
                    $this->release(60);
                    return;
                }

                // Ensure we don't use the same user agent twice in a row
                do {
                    $userAgent = $mobileUserAgents[array_rand($mobileUserAgents)];
                } while ($userAgent === $lastUsedAgent && count($mobileUserAgents) > 1);
                $lastUsedAgent = $userAgent;

                Log::info('Attempting to scrape Google', [
                    'keyword_id' => $this->keyword->id,
                    'keyword' => $this->keyword->keyword,
                    'attempt' => $retries + 1,
                    'user_agent' => $userAgent
                ]);

                // Add some randomization to the request parameters
                $params = [
                    'q' => $query,
                    'num' => rand(8, 10), // Randomize results count
                    'ie' => 'UTF-8',
                    'source' => 'mobile',
                    'gbv' => 1,
                    'pws' => 0, // Disable personalized results
                    'gl' => 'us', // Force US results
                    'hl' => 'en' // Force English language
                ];

                // Add random parameters to appear more natural
                if (rand(0, 1)) {
                    $params['safe'] = 'active';
                }

                $proxy = $this->proxyManager->getNextProxy();
                $requestOptions = [
                    'timeout' => $this->timeout,
                    'verify' => !empty($proxy), // Disable SSL verification when using proxy
                ];

                if ($proxy) {
                    $requestOptions['proxy'] = $proxy;
                    Log::info('Using proxy for request', [
                        'keyword_id' => $this->keyword->id,
                        'proxy' => $proxy
                    ]);
                }

                $response = Http::withHeaders([
                    'User-Agent' => $userAgent,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Connection' => 'keep-alive',
                    'Cache-Control' => 'max-age=0',
                    'Upgrade-Insecure-Requests' => '1',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'none',
                    'Sec-Fetch-User' => '?1'
                ])
                ->withOptions($requestOptions)
                ->get('https://www.google.com/search', $params);

                Log::info('Google response received', [
                    'keyword_id' => $this->keyword->id,
                    'status_code' => $response->status(),
                    'content_length' => strlen($response->body())
                ]);

                if ($response->successful()) {
                    $html = $response->body();
                    
                    // Parse results using the dedicated parser
                    $results = $this->resultParser->parse($html);

                    if (!empty($results)) {
                        // Store results in the database
                        foreach ($results as $result) {
                            $this->keyword->results()->create([
                                'title' => $result['title'] ?? null,
                                'url' => $result['url'] ?? null,
                                'snippet' => $result['snippet'] ?? $result['content'] ?? null,
                                'position' => $result['position'],
                                'type' => $result['type'],
                                'metadata' => json_encode($result['metadata'] ?? [])
                            ]);
                        }

                        Log::info('Successfully scraped and stored results', [
                            'keyword_id' => $this->keyword->id,
                            'results_count' => count($results)
                        ]);

                        break; // Exit retry loop on success
                    } else {
                        // Save HTML for debugging
                        if (app()->environment('local')) {
                            Storage::disk('local')->put('google_response_' . $this->keyword->id . '.html', $html);
                        }
                        
                        Log::warning('No results found in HTML', [
                            'keyword_id' => $this->keyword->id,
                            'html_sample' => substr($html, 0, 500)
                        ]);
                    }
                } else {
                    Log::warning('Google request failed', [
                        'keyword_id' => $this->keyword->id,
                        'status_code' => $response->status(),
                        'response' => substr($response->body(), 0, 500)
                    ]);

                    if ($proxy) {
                        $this->proxyManager->markProxyUnhealthy($proxy);
                    }
                    $this->rateLimiter->trackFailure($proxy ?? 'default');
                }

                // Add exponential backoff with jitter for retries
                $baseDelay = pow(2, $retries); // 1, 2, 4 seconds
                $jitter = rand(0, 1000) / 1000; // Random value between 0 and 1
                $delay = $baseDelay + $jitter;
                
                Log::info("Waiting {$delay} seconds before next attempt");
                usleep($delay * 1000000); // Convert to microseconds
                $retries++;
            } catch (\Exception $e) {
                Log::error('Error in Google scraping attempt: ' . $e->getMessage(), [
                    'keyword_id' => $this->keyword->id,
                    'attempt' => $retries + 1,
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString()
                ]);
                $retries++;
                usleep(rand(3000000, 7000000)); // Random delay between 3 and 7 seconds
            }
        }

        if (empty($results)) {
            throw new \Exception('Failed to scrape Google results after ' . $maxRetries . ' attempts');
        }

        return $results;
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Job failed: ' . $exception->getMessage(), [
            'keyword_id' => $this->keyword->id,
            'keyword' => $this->keyword->keyword
        ]);

        $this->keyword->update([
            'status' => 'failed',
            'results' => ['error' => $exception->getMessage()]
        ]);
    }
}
