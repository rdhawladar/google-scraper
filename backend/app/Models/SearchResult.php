<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'keyword_id',
        'total_ads',
        'total_links',
        'html_cache',
        'organic_results',
        'status',
        'error_message',
        'scraped_at'
    ];

    protected $casts = [
        'organic_results' => 'array',
        'scraped_at' => 'datetime',
        'total_ads' => 'integer',
        'total_links' => 'integer'
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('scraped_at', 'desc')->limit($limit);
    }
}
