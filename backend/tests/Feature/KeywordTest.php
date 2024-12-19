<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Keyword;
use App\Models\SearchResult;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class KeywordTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user and authenticate
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);

        // Setup fake storage
        Storage::fake('local');
    }

    public function test_can_create_keyword()
    {
        $keyword = Keyword::factory()->create([
            'user_id' => $this->user->id,
            'keyword' => 'test keyword',
            'status' => 'pending'
        ]);

        $this->assertDatabaseHas('keywords', [
            'id' => $keyword->id,
            'user_id' => $this->user->id,
            'keyword' => 'test keyword',
            'status' => 'pending'
        ]);
    }

    public function test_can_upload_keywords_csv()
    {
        $csvContent = "keyword\ntest keyword 1\ntest keyword 2";
        $file = UploadedFile::fake()->createWithContent('keywords.csv', $csvContent);

        $response = $this->postJson('/api/keywords/upload', [
            'file' => $file
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'keyword',
                        'status',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);

        $this->assertDatabaseHas('keywords', [
            'user_id' => $this->user->id,
            'keyword' => 'test keyword 1'
        ]);

        $this->assertDatabaseHas('keywords', [
            'user_id' => $this->user->id,
            'keyword' => 'test keyword 2'
        ]);
    }

    public function test_can_get_keyword_list()
    {
        $keywords = Keyword::factory()->count(5)->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->getJson('/api/keywords');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'keyword',
                        'status',
                        'results' => [],
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);
    }

    public function test_can_get_keyword_with_search_results()
    {
        $keyword = Keyword::factory()->create([
            'user_id' => $this->user->id
        ]);

        SearchResult::factory()->create([
            'keyword_id' => $keyword->id
        ]);

        $response = $this->getJson("/api/keywords/{$keyword->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'keyword',
                    'status',
                    'results' => [
                        '*' => [
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
                        ]
                    ],
                    'created_at',
                    'updated_at'
                ]
            ]);
    }

    public function test_cannot_upload_invalid_file()
    {
        $file = UploadedFile::fake()->create('test.txt', 100);

        $response = $this->postJson('/api/keywords/upload', [
            'file' => $file
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_cannot_upload_empty_csv()
    {
        $file = UploadedFile::fake()->create('keywords.csv', 0, 'text/csv');

        $response = $this->postJson('/api/keywords/upload', [
            'file' => $file
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_cannot_access_other_users_keywords()
    {
        $otherUser = User::factory()->create();
        $keyword = Keyword::factory()->create([
            'user_id' => $otherUser->id
        ]);

        $response = $this->getJson("/api/keywords/{$keyword->id}");
        $response->assertStatus(403);
    }

    public function test_can_retry_failed_keyword()
    {
        $keyword = Keyword::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'failed'
        ]);

        $response = $this->postJson("/api/keywords/{$keyword->id}/retry");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Keyword queued for retry'
            ]);

        $this->assertDatabaseHas('keywords', [
            'id' => $keyword->id,
        ]);
    }
}
