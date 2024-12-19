<?php

namespace App\Http\Controllers;

use App\Models\SearchResult;
use Illuminate\Http\Request;

class SearchResultController extends Controller
{
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
