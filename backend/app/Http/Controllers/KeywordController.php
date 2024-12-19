<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Jobs\ScrapeGoogleResults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Keywords",
 *     description="API Endpoints for managing keywords"
 * )
 */
class KeywordController extends Controller
{
    private const MAX_KEYWORDS_PER_UPLOAD = 100;

    /**
     * @OA\Get(
     *     path="/api/keywords",
     *     summary="Get all keywords",
     *     description="Returns a list of all keywords for the authenticated user",
     *     operationId="getKeywords",
     *     tags={"Keywords"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="keyword", type="string", example="cloud computing"),
     *                 @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed"}, example="completed"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function index()
    {
        $keywords = Auth::user()->keywords()->orderBy('created_at', 'desc')->get();
        return response()->json($keywords);
    }

    /**
     * @OA\Post(
     *     path="/api/keywords/upload",
     *     summary="Upload keywords",
     *     description="Upload a CSV file containing keywords to scrape",
     *     operationId="uploadKeywords",
     *     tags={"Keywords"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="CSV file containing keywords (max 1MB)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Keywords uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="5 keywords uploaded successfully"),
     *             @OA\Property(
     *                 property="keywords",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="keyword", type="string", example="cloud computing"),
     *                     @OA\Property(property="status", type="string", example="pending"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="file",
     *                     type="array",
     *                     @OA\Items(type="string", example="Maximum 100 keywords allowed per upload. Found: 101 keywords.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
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

    /**
     * @OA\Get(
     *     path="/api/keywords/{keyword}",
     *     summary="Get keyword details",
     *     description="Returns details of a specific keyword",
     *     operationId="getKeyword",
     *     tags={"Keywords"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="keyword",
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
     *             @OA\Property(property="keyword", type="string", example="cloud computing"),
     *             @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed"}, example="completed"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Keyword not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function show(Keyword $keyword)
    {
        // Ensure the user can only view their own keywords
        if ($keyword->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($keyword);
    }

    /**
     * @OA\Post(
     *     path="/api/keywords/{keyword}/retry",
     *     summary="Retry failed keyword",
     *     description="Retries scraping for a failed keyword",
     *     operationId="retryKeyword",
     *     tags={"Keywords"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="keyword",
     *         in="path",
     *         description="Keyword ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Keyword queued for retry",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Keyword queued for retry")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Keyword not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
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
