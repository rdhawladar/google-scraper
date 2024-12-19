<?php

namespace App\Http\Controllers;

use App\Models\SearchResult;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Search Results",
 *     description="API Endpoints for managing search results"
 * )
 */
class SearchResultController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/search-results/{keywordId}",
     *     summary="Get search results",
     *     description="Returns the latest search results for a specific keyword",
     *     operationId="getSearchResults",
     *     tags={"Search Results"},
     *     @OA\Parameter(
     *         name="keywordId",
     *         in="path",
     *         description="Keyword ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="keyword_id", type="integer", example=1),
     *             @OA\Property(property="total_ads", type="integer", example=3),
     *             @OA\Property(property="total_links", type="integer", example=10),
     *             @OA\Property(
     *                 property="organic_results",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="title", type="string", example="Cloud Computing Services - AWS"),
     *                     @OA\Property(property="url", type="string", example="https://aws.amazon.com"),
     *                     @OA\Property(property="snippet", type="string", example="Amazon Web Services offers reliable, scalable, and inexpensive cloud computing services.")
     *                 )
     *             ),
     *             @OA\Property(property="status", type="string", enum={"completed", "failed"}, example="completed"),
     *             @OA\Property(property="error_message", type="string", nullable=true),
     *             @OA\Property(property="scraped_at", type="string", format="date-time"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Keyword not found or no search results available"
     *     )
     * )
     */
    public function show($keywordId)
    {
        // Get the latest successful search result for the keyword
        $searchResult = SearchResult::where('keyword_id', $keywordId)
            ->where('status', 'success')
            ->latest('scraped_at')
            ->first();

        if (!$searchResult) {
            return response()->json(['message' => 'No search results found'], 404);
        }

        return response()->json($searchResult);
    }
}
