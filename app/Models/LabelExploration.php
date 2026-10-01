<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabelExploration extends Model
{
    protected $fillable = [
        'user_id',
        'discogs_label_id',
        'label_name',
        'status',
        'progress',
        'message',
        'results',
        'failed_reason',
    ];

    protected function casts(): array
    {
        return [
            'discogs_label_id' => 'integer',
            'progress' => 'integer',
            'results' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
