<?php

namespace App\Models;

use Database\Factories\SearchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Search extends Model
{
    /** @use HasFactory<SearchFactory> */
    use HasFactory;

    protected $fillable = ['type', 'query', 'parameters', 'result_count'];

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
}
