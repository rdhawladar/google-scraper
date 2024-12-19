<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Jobs\ScrapeGoogleResults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class KeywordController extends Controller
{
    private const MAX_KEYWORDS_PER_UPLOAD = 100;

    public function index()
    {
        $keywords = Auth::user()->keywords()->orderBy('created_at', 'desc')->get();
        return response()->json($keywords);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:1024', // max 1MB
        ]);

        try {
            $file = $request->file('file');
            $keywords = array_map('str_getcsv', file($file->getPathname()));
            
            // Flatten the array and remove empty lines
            $keywords = array_filter(array_map(function($row) {
                return trim($row[0]);
            }, $keywords));

            // Remove header row if it exists
            if (count($keywords) > 0 && !is_numeric($keywords[0])) {
                array_shift($keywords);
            }

            // Check keyword limit per upload
            if (count($keywords) > self::MAX_KEYWORDS_PER_UPLOAD) {
                throw ValidationException::withMessages([
                    'file' => [
                        sprintf(
                            'Maximum %d keywords allowed per upload. Found: %d keywords.',
                            self::MAX_KEYWORDS_PER_UPLOAD,
                            count($keywords)
                        )
                    ]
                ]);
            }

            $createdKeywords = [];
            
            // Create keyword records and dispatch jobs
            foreach ($keywords as $keyword) {
                $keywordModel = Auth::user()->keywords()->create([
                    'keyword' => $keyword,
                    'status' => 'pending'
                ]);
                
                // Dispatch job with random delay to avoid overwhelming Google
                ScrapeGoogleResults::dispatch($keywordModel)
                    ->delay(now()->addSeconds(rand(1, 10)));
                
                $createdKeywords[] = $keywordModel;
            }

            return response()->json([
                'message' => count($createdKeywords) . ' keywords uploaded successfully',
                'keywords' => $createdKeywords
            ]);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Error uploading keywords: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error uploading keywords: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Keyword $keyword)
    {
        // Ensure the user can only view their own keywords
        if ($keyword->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($keyword);
    }

    public function retry(Keyword $keyword)
    {
        // Ensure the user can only retry their own keywords
        if ($keyword->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Only retry failed keywords
        if ($keyword->status !== 'failed') {
            return response()->json([
                'message' => 'Only failed keywords can be retried'
            ], 400);
        }

        $keyword->update(['status' => 'pending']);
        ScrapeGoogleResults::dispatch($keyword);

        return response()->json([
            'message' => 'Keyword queued for retry'
        ]);
    }
}
