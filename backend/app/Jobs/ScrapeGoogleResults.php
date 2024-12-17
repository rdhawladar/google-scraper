<?php

namespace App\Jobs;

use App\Models\Keyword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use DOMDocument;

class ScrapeGoogleResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $keyword;
    protected $maxRetries = 1;
    protected $timeout = 30;

    public function __construct(Keyword $keyword)
    {
        $this->keyword = $keyword;
    }

    public function handle()
    {
        try {
            $this->keyword->update(['status' => 'processing']);
            
            $results = $this->scrapeGoogle($this->keyword->keyword);
            
            $this->keyword->update([
                'status' => 'completed',
                'results' => $results
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
                ->timeout($this->timeout)
                ->get('https://www.google.com/search', $params);

                Log::info('Google response received', [
                    'keyword_id' => $this->keyword->id,
                    'status_code' => $response->status(),
                    'content_length' => strlen($response->body())
                ]);

                if ($response->successful()) {
                    $html = $response->body();
                    
                    // Parse the HTML
                    $dom = new DOMDocument();
                    @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

                    // Find all anchor tags
                    $links = $dom->getElementsByTagName('a');
                    $foundResults = false;

                    foreach ($links as $link) {
                        $href = $link->getAttribute('href');
                        
                        // Skip if not a valid URL or if it's a Google internal link
                        if (!filter_var($href, FILTER_VALIDATE_URL) || 
                            str_contains($href, 'google.com') || 
                            str_contains($href, '/url?') ||
                            str_contains($href, '/search?')) {
                            continue;
                        }

                        // Try to find the title and snippet near this link
                        $title = null;
                        $snippet = null;
                        
                        // Look for title in parent nodes
                        $parent = $link;
                        for ($i = 0; $i < 3; $i++) {
                            if (!$parent) break;
                            
                            // Check all child nodes for potential title or heading
                            foreach ($parent->childNodes as $child) {
                                if ($child->nodeType === XML_ELEMENT_NODE) {
                                    $text = trim($child->textContent);
                                    if (strlen($text) > 10 && strlen($text) < 200) {
                                        $title = $text;
                                        break 2;
                                    }
                                }
                            }
                            $parent = $parent->parentNode;
                        }

                        // Look for snippet in nearby nodes
                        $parent = $link->parentNode;
                        for ($i = 0; $i < 3 && $parent; $i++) {
                            $next = $parent->nextSibling;
                            while ($next) {
                                if ($next->nodeType === XML_ELEMENT_NODE) {
                                    $text = trim($next->textContent);
                                    if (strlen($text) > 50) {
                                        $snippet = $text;
                                        break 2;
                                    }
                                }
                                $next = $next->nextSibling;
                            }
                            $parent = $parent->parentNode;
                        }

                        // Only add if we found both title and URL
                        if ($title && $href) {
                            $results[] = [
                                'title' => $title,
                                'url' => $href,
                                'snippet' => $snippet
                            ];
                            $foundResults = true;
                        }
                    }

                    if ($foundResults) {
                        break;
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
