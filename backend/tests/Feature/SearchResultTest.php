<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Keyword;
use App\Models\SearchResult;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SearchResultTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user and authenticate
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_can_get_search_results_for_keyword()
    {
        $keyword = Keyword::factory()->create([
            'user_id' => $this->user->id
        ]);

        SearchResult::factory()->create([
            'keyword_id' => $keyword->id,
            'total_ads' => 2,
            'total_links' => 10,
            'html_cache' => '',
            'organic_results' => [],
            'status' => 'success',
            'error_message' => null,
            'scraped_at' => '2024-12-19 18:30:07'
        ]);
        

        $response = $this->getJson("/api/search-results/{$keyword->id}");


        $response->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'keyword_id',
                'total_ads',
                'total_links',
                'html_cache',
                'organic_results',
                'status',
                'error_message',
                'scraped_at',
                'created_at',
                'updated_at'
            ]);
    }

    public function test_search_result_belongs_to_keyword()
    {
        $keyword = Keyword::factory()->create([
            'user_id' => $this->user->id
        ]);

        $searchResult = SearchResult::factory()->create([
            'keyword_id' => $keyword->id
        ]);

        $this->assertTrue($searchResult->keyword->is($keyword));
    }

    public function test_returns_404_for_nonexistent_keyword()
    {
        $response = $this->getJson("/api/search-results/99999");
        $response->assertStatus(404);
    }

    public function test_cannot_access_other_users_search_results()
    {
        $otherUser = User::factory()->create();
        $keyword = Keyword::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $searchResult = SearchResult::factory()->create([
            'keyword_id' => $keyword->id
        ]);

        $response = $this->getJson("/api/search-results/{$keyword->id}");
        $response->assertStatus(404);
    }
}
