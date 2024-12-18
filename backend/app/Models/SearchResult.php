<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchResult extends Model
{
    protected $fillable = [
        'keyword_id',
        'title',
        'url',
        'snippet',
        'position',
        'type',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'position' => 'integer'
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
