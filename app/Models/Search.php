<?php

namespace App\Models;

use Database\Factories\SearchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Search extends Model
{
    /** @use HasFactory<SearchFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'type', 'query', 'parameters', 'result_count'];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'result_count' => 'integer',
        ];
    }

    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class)->withPivot('position');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
