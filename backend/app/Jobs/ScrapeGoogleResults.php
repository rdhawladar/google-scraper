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
use DOMDocument;
use DOMXPath;

class ScrapeGoogleResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $keyword;
    protected $maxRetries = 3;
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
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0'
        ];

        $results = [];
        $retries = 0;

        while ($retries < $this->maxRetries) {
            try {
                $userAgent = $userAgents[array_rand($userAgents)];
                $response = Http::withHeaders([
                    'User-Agent' => $userAgent,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.5',
                    'Accept-Encoding' => 'gzip, deflate',
                    'DNT' => '1',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1'
                ])
                ->timeout($this->timeout)
                ->get('https://www.google.com/search', [
                    'q' => $query,
                    'num' => 10,
                    'hl' => 'en'
                ]);

                if ($response->successful()) {
                    $html = $response->body();
                    
                    // Parse the HTML
                    $dom = new DOMDocument();
                    @$dom->loadHTML($html, LIBXML_NOERROR);
                    $xpath = new DOMXPath($dom);

                    // Extract search results
                    $searchResults = $xpath->query('//div[@class="g"]');
                    foreach ($searchResults as $result) {
                        $titleNode = $xpath->query('.//h3', $result)->item(0);
                        $linkNode = $xpath->query('.//a', $result)->item(0);
                        $snippetNode = $xpath->query('.//div[@class="VwiC3b yXK7lf MUxGbd yDYNvb lyLwlc lEBKkf"]', $result)->item(0);

                        if ($titleNode && $linkNode) {
                            $results[] = [
                                'title' => $titleNode->textContent,
                                'url' => $linkNode->getAttribute('href'),
                                'snippet' => $snippetNode ? $snippetNode->textContent : null
                            ];
                        }
                    }

                    // If we successfully got results, break the retry loop
                    if (!empty($results)) {
                        break;
                    }
                }

                // Add delay between retries to avoid rate limiting
                sleep(rand(2, 5));
                $retries++;
            } catch (\Exception $e) {
                Log::error('Error in Google scraping attempt: ' . $e->getMessage());
                $retries++;
                sleep(rand(2, 5));
            }
        }

        if (empty($results)) {
            throw new \Exception('Failed to scrape Google results after ' . $this->maxRetries . ' attempts');
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
