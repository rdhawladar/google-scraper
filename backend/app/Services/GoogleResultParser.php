<?php

namespace App\Services;

use DOMDocument;
use DOMNode;
use Illuminate\Support\Facades\Log;

class GoogleResultParser
{
    private const RESULT_SELECTORS = [
        'organic' => [
            'container' => ['div.g', 'div.Gx5Zad', 'div[jscontroller]'],
            'title' => ['h3', 'div.vvjwJb', 'div.fc9yUc'],
            'link' => ['a[ping]', 'a[data-ved]', 'a[jsname]'],
            'snippet' => ['div.VwiC3b', 'div.s3v9rd', 'div[role="heading"]']
        ],
        'featured_snippet' => [
            'container' => ['div.xpdopen', 'div.g.kno-result', 'div.ifM9O'],
            'title' => ['h3', 'div.title'],
            'content' => ['div.LGOjhe', 'div.IZ6rdc']
        ]
    ];

    public function parse(string $html): array
    {
        $results = [];
        $position = 1;

        try {
            $dom = new DOMDocument();
            @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

            // First, try to find featured snippets
            $featuredSnippet = $this->extractFeaturedSnippet($dom);
            if ($featuredSnippet) {
                $featuredSnippet['position'] = 0;
                $featuredSnippet['type'] = 'featured_snippet';
                $results[] = $featuredSnippet;
            }

            // Then find organic results
            foreach ($this->findResultContainers($dom) as $container) {
                $result = $this->parseResultContainer($container, $position);
                if ($result) {
                    $results[] = $result;
                    $position++;
                }
            }

            Log::info('Parsed search results', [
                'total_results' => count($results),
                'has_featured_snippet' => !empty($featuredSnippet)
            ]);
        } catch (\Exception $e) {
            Log::error('Error parsing Google results: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $results;
    }

    private function findResultContainers(DOMDocument $dom): array
    {
        $containers = [];
        foreach (self::RESULT_SELECTORS['organic']['container'] as $selector) {
            $elements = $this->querySelectorAll($dom, $selector);
            if (!empty($elements)) {
                $containers = array_merge($containers, $elements);
            }
        }
        return $containers;
    }

    private function parseResultContainer(DOMNode $container, int $position): ?array
    {
        $result = [
            'position' => $position,
            'type' => 'organic'
        ];

        // Find title and link
        $titleNode = null;
        foreach (self::RESULT_SELECTORS['organic']['title'] as $selector) {
            $titleNode = $this->querySelector($container, $selector);
            if ($titleNode) break;
        }

        $linkNode = null;
        foreach (self::RESULT_SELECTORS['organic']['link'] as $selector) {
            $linkNode = $this->querySelector($container, $selector);
            if ($linkNode) break;
        }

        if (!$titleNode || !$linkNode) {
            return null;
        }

        $result['title'] = trim($titleNode->textContent);
        $result['url'] = $linkNode->getAttribute('href');

        // Clean URL if it's a Google redirect
        if (strpos($result['url'], '/url?') === 0) {
            parse_str(parse_url($result['url'], PHP_URL_QUERY), $params);
            $result['url'] = $params['q'] ?? $result['url'];
        }

        // Find snippet
        foreach (self::RESULT_SELECTORS['organic']['snippet'] as $selector) {
            $snippetNode = $this->querySelector($container, $selector);
            if ($snippetNode) {
                $result['snippet'] = trim($snippetNode->textContent);
                break;
            }
        }

        // Extract additional metadata if available
        $result['metadata'] = $this->extractMetadata($container);

        return $result;
    }

    private function extractFeaturedSnippet(DOMDocument $dom): ?array
    {
        foreach (self::RESULT_SELECTORS['featured_snippet']['container'] as $selector) {
            $container = $this->querySelector($dom, $selector);
            if (!$container) continue;

            $snippet = [];

            // Extract title
            foreach (self::RESULT_SELECTORS['featured_snippet']['title'] as $titleSelector) {
                $titleNode = $this->querySelector($container, $titleSelector);
                if ($titleNode) {
                    $snippet['title'] = trim($titleNode->textContent);
                    break;
                }
            }

            // Extract content
            foreach (self::RESULT_SELECTORS['featured_snippet']['content'] as $contentSelector) {
                $contentNode = $this->querySelector($container, $contentSelector);
                if ($contentNode) {
                    $snippet['content'] = trim($contentNode->textContent);
                    break;
                }
            }

            if (!empty($snippet)) {
                return $snippet;
            }
        }

        return null;
    }

    private function extractMetadata(DOMNode $container): array
    {
        $metadata = [];

        // Look for date
        $dateNode = $this->querySelector($container, 'span.MUxGbd.wuQ4Ob.WZ8Tjf');
        if ($dateNode) {
            $metadata['date'] = trim($dateNode->textContent);
        }

        // Look for rating
        $ratingNode = $this->querySelector($container, 'span.Fam1ne.EBe2gf');
        if ($ratingNode) {
            $metadata['rating'] = trim($ratingNode->textContent);
        }

        return $metadata;
    }

    private function querySelector(DOMNode $context, string $selector): ?DOMNode
    {
        try {
            $xpath = $this->convertCssToXPath($selector);
            $domXPath = new \DOMXPath($context instanceof DOMDocument ? $context : $context->ownerDocument);
            return $domXPath->query($xpath, $context)->item(0);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function querySelectorAll(DOMNode $context, string $selector): array
    {
        try {
            $xpath = $this->convertCssToXPath($selector);
            $domXPath = new \DOMXPath($context instanceof DOMDocument ? $context : $context->ownerDocument);
            $nodes = $domXPath->query($xpath, $context);
            return $nodes ? iterator_to_array($nodes) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function convertCssToXPath(string $selector): string
    {
        // Simple CSS to XPath conversion for common selectors
        $xpath = '';
        
        // Handle element with class
        if (preg_match('/^([a-z0-9]+)\.([a-zA-Z0-9_-]+)$/', $selector, $matches)) {
            $xpath = ".//{$matches[1]}[contains(@class, '{$matches[2]}')]";
        }
        // Handle element with attribute
        elseif (preg_match('/^([a-z0-9]+)\[([^\]]+)\]$/', $selector, $matches)) {
            $xpath = ".//{$matches[1]}[@{$matches[2]}]";
        }
        // Handle simple element
        else {
            $xpath = ".//{$selector}";
        }

        return $xpath;
    }
}
