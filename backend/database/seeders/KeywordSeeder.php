<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Keyword;
use App\Models\SearchResult;

class KeywordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test user if not exists
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => bcrypt('password'),
            ]
        );

        // Pending Keywords
        $pendingKeywords = [
            ['keyword' => 'best laptop 2024', 'status' => 'pending'],
            ['keyword' => 'smartphone reviews', 'status' => 'pending'],
        ];

        // Processing Keywords
        $processingKeywords = [
            ['keyword' => 'gaming pc build guide', 'status' => 'processing'],
            ['keyword' => 'mechanical keyboard comparison', 'status' => 'processing'],
        ];

        // Completed Keywords with Results
        $completedKeywords = [
            [
                'keyword' => 'wireless earbuds',
                'status' => 'completed',
                'results' => [
                    'total_ads' => 3,
                    'total_links' => 10,
                    'organic_results' => [
                        ['title' => 'Best Wireless Earbuds 2024', 'url' => 'https://example.com/1'],
                        ['title' => 'Top 10 Wireless Earbuds', 'url' => 'https://example.com/2'],
                    ]
                ]
            ],
            [
                'keyword' => 'smart watch features',
                'status' => 'completed',
                'results' => [
                    'total_ads' => 2,
                    'total_links' => 8,
                    'organic_results' => [
                        ['title' => 'Smart Watch Buying Guide', 'url' => 'https://example.com/3'],
                        ['title' => 'Compare Smart Watches', 'url' => 'https://example.com/4'],
                    ]
                ]
            ],
        ];

        // Failed Keywords
        $failedKeywords = [
            ['keyword' => 'invalid search term !!!', 'status' => 'failed'],
            ['keyword' => 'blocked keyword example', 'status' => 'failed'],
        ];

        // Insert all keywords
        foreach (array_merge($pendingKeywords, $processingKeywords, $failedKeywords) as $keywordData) {
            $keyword = Keyword::create([
                'user_id' => $user->id,
                'keyword' => $keywordData['keyword'],
                'status' => $keywordData['status'],
            ]);

            // Create a failed search result for failed keywords
            if ($keywordData['status'] === 'failed') {
                SearchResult::create([
                    'keyword_id' => $keyword->id,
                    'status' => 'failed',
                    'error_message' => 'Example error message for failed scraping attempt',
                    'scraped_at' => now(),
                ]);
            }
        }

        // Insert completed keywords with their results
        foreach ($completedKeywords as $keywordData) {
            $keyword = Keyword::create([
                'user_id' => $user->id,
                'keyword' => $keywordData['keyword'],
                'status' => $keywordData['status'],
                'results' => $keywordData['results'],
            ]);

            // Create successful search result
            SearchResult::create([
                'keyword_id' => $keyword->id,
                'status' => 'success',
                'total_ads' => $keywordData['results']['total_ads'],
                'total_links' => $keywordData['results']['total_links'],
                'organic_results' => $keywordData['results']['organic_results'],
                'scraped_at' => now(),
            ]);
        }
    }
}
