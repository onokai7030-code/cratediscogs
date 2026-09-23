<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Style extends Model
{
    protected $fillable = ['genre_id', 'name', 'normalized_name'];

    public function genre(): BelongsTo
    {
        return $this->belongsTo(Genre::class);
    }

    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class);
    }
}
