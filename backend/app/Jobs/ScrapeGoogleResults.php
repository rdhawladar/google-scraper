<?php

namespace App\Jobs;

use App\Models\Keyword;
use App\Models\SearchResult;
use App\Services\GoogleResultParser;
use App\Services\ProxyManager;
use App\Services\RateLimiter;
use App\Services\ScraperMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ScrapeGoogleResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $keyword;
    private $maxRetries = 3;
    public $timeout = 120;
    public $tries = 3;
    public $backoff = [30, 60, 120]; // Wait 30s, 60s, then 120s between retries

    public function __construct(Keyword $keyword)
    {
        $this->keyword = $keyword;
        Log::info('ScrapeGoogleResults job constructed', [
            'keyword_id' => $keyword->id,
            'keyword' => $keyword->keyword,
            'current_status' => $keyword->status
        ]);
    }

    public function handle(
        GoogleResultParser $parser,
        ProxyManager $proxyManager,
        RateLimiter $rateLimiter,
        ScraperMonitor $monitor
    ) {
        Log::info('Starting to process keyword', [
            'keyword_id' => $this->keyword->id,
            'keyword' => $this->keyword->keyword,
            'attempt' => $this->attempts()
        ]);

        try {
            // Update keyword status to processing
            DB::beginTransaction();
            
            $updated = $this->keyword->update(['status' => 'processing']);
            Log::info('Updating keyword status to processing', [
                'keyword_id' => $this->keyword->id,
                'status_updated' => $updated,
                'new_status' => 'processing'
            ]);
            
            DB::commit();

            // Refresh the model to ensure we have the latest data
            $this->keyword->refresh();
            
            Log::info('Current keyword status after update', [
                'keyword_id' => $this->keyword->id,
                'current_status' => $this->keyword->status
            ]);

            // Check circuit breaker
            if ($monitor->isCircuitOpen()) {
                Log::warning('Circuit breaker is open, aborting scrape');
                throw new \Exception('Circuit breaker is open');
            }

            // Check rate limit before proceeding
            if (!$rateLimiter->canProceed()) {
                Log::info('Rate limit reached, releasing job back to queue', [
                    'keyword_id' => $this->keyword->id,
                    'delay' => 30
                ]);
                $this->release(30);
                return;
            }

            // Apply rate limiting
            $rateLimiter->throttle();
            Log::info('Rate limit applied successfully');

            // Get proxy
            $proxy = $proxyManager->getNextProxy();
            Log::info('Got proxy for request', ['proxy' => $proxy]);

            // Create search result record
            $searchResult = SearchResult::create([
                'keyword_id' => $this->keyword->id,
                'status' => 'pending',
                'scraped_at' => now()
            ]);

            Log::info('Making Google search request', [
                'keyword' => $this->keyword->keyword,
                'search_result_id' => $searchResult->id
            ]);

            // Make the request
            $response = Http::withOptions([
                'proxy' => $proxy,
                'verify' => false,
                'headers' => [
                    'User-Agent' => $this->getRandomUserAgent(),
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Connection' => 'keep-alive',
                ]
            ])->get('https://www.google.com/search', [
                'q' => $this->keyword->keyword,
                'hl' => 'en',
                'num' => 10
            ]);

            if (!$response->successful()) {
                Log::error('Google search request failed', [
                    'keyword_id' => $this->keyword->id,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
                throw new \Exception("HTTP request failed with status: " . $response->status());
            }

            Log::info('Successfully received Google search response', [
                'keyword_id' => $this->keyword->id,
                'status_code' => $response->status()
            ]);

            // Parse the results
            $results = $parser->parse($response->body());
            Log::info('Successfully parsed search results', [
                'keyword_id' => $this->keyword->id,
                'total_ads' => $results['total_ads'],
                'total_links' => $results['total_links']
            ]);

            DB::beginTransaction();

            // Update search result
            $searchResult->update([
                'total_ads' => $results['total_ads'],
                'total_links' => $results['total_links'],
                'html_cache' => $results['html_cache'],
                'organic_results' => $results['organic_results'],
                'status' => 'success',
                'scraped_at' => now()
            ]);

            // Update keyword status and results
            $updated = $this->keyword->update([
                'status' => 'completed',
                'results' => $results['organic_results'],
                'last_scraped_at' => now()
            ]);

            Log::info('Updating final keyword status', [
                'keyword_id' => $this->keyword->id,
                'status_updated' => $updated,
                'new_status' => 'completed',
                'search_result_id' => $searchResult->id
            ]);

            DB::commit();

            // Refresh the model to ensure we have the latest data
            $this->keyword->refresh();
            
            Log::info('Job completed successfully', [
                'keyword_id' => $this->keyword->id,
                'final_status' => $this->keyword->status,
                'search_result_id' => $searchResult->id
            ]);

            $monitor->recordSuccess();

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Scraping failed for keyword', [
                'keyword_id' => $this->keyword->id,
                'keyword' => $this->keyword->keyword,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attempt' => $this->attempts()
            ]);

            if ($this->attempts() < $this->tries) {
                // Get the backoff time for this attempt
                $backoffTime = $this->backoff[$this->attempts() - 1] ?? end($this->backoff);
                Log::info('Releasing job back to queue for retry', [
                    'keyword_id' => $this->keyword->id,
                    'title' => $this->keyword->keyword,
                    'attempt' => $this->attempts(),
                    'backoff_time' => $backoffTime
                ]);
                $this->release($backoffTime);
            } else {
                DB::beginTransaction();
                
                if (isset($searchResult)) {
                    $searchResult->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage()
                    ]);
                }

                // Update keyword status to failed after all retries
                $updated = $this->keyword->update([
                    'status' => 'failed',
                    'results' => ['error' => $e->getMessage()]
                ]);

                Log::error('All retry attempts exhausted, marking keyword as failed', [
                    'keyword_id' => $this->keyword->id,
                    'title' => $this->keyword->keyword,
                    'status_updated' => $updated,
                    'final_status' => 'failed',
                    'search_result_id' => $searchResult->id ?? null
                ]);

                DB::commit();

                $monitor->recordFailure($e->getMessage());
            }
        }
    }

    private function getRandomUserAgent(): string
    {
        $userAgents = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.2 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (iPad; CPU OS 14_7_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.1.2 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 14_7_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/92.0.4515.90 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 11; SM-G991B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Mobile Safari/537.36',
            'Mozilla/5.0 (Linux; Android 11; SM-G991U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.159 Mobile Safari/537.36'
        ];

        return $userAgents[array_rand($userAgents)];
    }
}
