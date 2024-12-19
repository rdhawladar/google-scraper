<?php

namespace Database\Factories;

use App\Models\SearchResult;
use Illuminate\Database\Eloquent\Factories\Factory;

class SearchResultFactory extends Factory
{
    protected $model = SearchResult::class;

    public function definition()
    {
        return [
            'keyword_id' => \App\Models\Keyword::factory(),
            'total_ads' => $this->faker->numberBetween(0, 5),
            'total_links' => $this->faker->numberBetween(5, 15),
            'organic_results' => [
                [
                    'title' => $this->faker->sentence,
                    'url' => $this->faker->url,
                    'snippet' => $this->faker->paragraph
                ]
            ],
            'html_cache' => $this->generateHtmlCache(),
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed']),
            'error_message' => null,
            'scraped_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function generateHtmlCache()
    {
        $title = $this->faker->sentence;
        $url = $this->faker->url;
        $description = $this->faker->paragraph;

        return <<<HTML
        <html>
            <body>
                <div class="g">
                    <h3><a href="{$url}">{$title}</a></h3>
                    <div class="VwiC3b">{$description}</div>
                </div>
            </body>
        </html>
        HTML;
    }
}
