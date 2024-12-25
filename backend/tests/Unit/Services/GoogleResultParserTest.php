<?php

namespace Tests\Unit\Services;

use App\Services\GoogleResultParser;
use Tests\TestCase;
use Illuminate\Support\Facades\Log;

class GoogleResultParserTest extends TestCase
{
    private GoogleResultParser $parser;
    private string $sampleHtml;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new GoogleResultParser();
        
        // Mock the Log facade
        Log::shouldReceive('info')->andReturn(null);
        Log::shouldReceive('error')->andReturn(null);
        
        // Sample HTML that mimics Google search results
        $this->sampleHtml = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <div class="g">
        <h3>Sample Title</h3>
        <a href="https://example.com" data-ved="123">Link Text</a>
        <div class="VwiC3b">Sample snippet text</div>
        <span class="MUxGbd wuQ4Ob WZ8Tjf">Jan 1, 2024</span>
    </div>
    <div class="xpdopen">
        <h3>Featured Snippet Title</h3>
        <div class="LGOjhe">Featured snippet content</div>
    </div>
    <div class="Gx5Zad">
        <div class="vvjwJb">Another Result</div>
        <a href="/url?q=https://another-example.com&amp;sa=U" jsname="link">Another Link</a>
        <div class="s3v9rd">Another snippet</div>
    </div>
</body>
</html>
HTML;
    }

    public function testParseExtractsOrganicResults()
    {
        $results = $this->parser->parse($this->sampleHtml);
        
        $this->assertArrayHasKey('organic_results', $results);
        $this->assertIsArray($results['results']);
        
        // Check first organic result
        $firstResult = $results['results'][1] ?? null; // Index 1 because featured snippet is at 0
        $this->assertNotNull($firstResult);
        $this->assertEquals('Sample Title', $firstResult['title']);
        $this->assertEquals('https://example.com', $firstResult['url']);
        $this->assertEquals('Sample snippet text', $firstResult['snippet']);
        $this->assertEquals(1, $firstResult['position']);
        $this->assertEquals('organic', $firstResult['type']);
    }

    public function testParseExtractsFeaturedSnippet()
    {
        $results = $this->parser->parse($this->sampleHtml);
        
        $this->assertIsArray($results['results']);
        $featuredSnippet = $results['results'][0] ?? null;
        
        $this->assertNotNull($featuredSnippet);
        $this->assertEquals('Featured Snippet Title', $featuredSnippet['title']);
        $this->assertEquals('Featured snippet content', $featuredSnippet['content']);
        $this->assertEquals(0, $featuredSnippet['position']);
        $this->assertEquals('featured_snippet', $featuredSnippet['type']);
    }

    public function testParseHandlesGoogleRedirectUrls()
    {
        $results = $this->parser->parse($this->sampleHtml);
        
        // Check the second organic result which has a Google redirect URL
        $secondResult = $results['results'][2] ?? null;
        $this->assertNotNull($secondResult);
        $this->assertEquals('https://another-example.com', $secondResult['url']);
    }

    public function testParseExtractsMetadata()
    {
        // Create a test HTML with proper Google search result structure
        $htmlWithMetadata = <<<HTML
<!DOCTYPE html>
<html>
<body>
    <div class="g">
        <h3>Test Result</h3>
        <a href="https://example.com" data-ved="123">Link</a>
        <div class="VwiC3b">Snippet text</div>
        <span class="MUxGbd wuQ4Ob WZ8Tjf">Jan 1, 2024</span>
        <span class="Fam1ne EBe2gf">4.5</span>
    </div>
</body>
</html>
HTML;

        $results = $this->parser->parse($htmlWithMetadata);
        
        $firstResult = $results['results'][0] ?? null;
        $this->assertNotNull($firstResult);
        $this->assertArrayHasKey('metadata', $firstResult);
        $this->assertArrayHasKey('date', $firstResult['metadata']);
        $this->assertArrayHasKey('rating', $firstResult['metadata']);
        $this->assertEquals('Jan 1, 2024', $firstResult['metadata']['date']);
        $this->assertEquals('4.5', $firstResult['metadata']['rating']);
    }

    public function testParseHandlesEmptyHtml()
    {
        $results = $this->parser->parse('');
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('results', $results);
        $this->assertEmpty($results['results']);
    }

    public function testParseHandlesInvalidHtml()
    {
        $results = $this->parser->parse('<invalid>html</unclosed');
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('results', $results);
        $this->assertEmpty($results['results']);
    }
}
