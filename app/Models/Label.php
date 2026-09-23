<?php

namespace App\Models;

use Database\Factories\LabelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Label extends Model
{
    /** @use HasFactory<LabelFactory> */
    use HasFactory;

    protected $fillable = ['discogs_id', 'name', 'normalized_name', 'parent_id'];

    protected function casts(): array
    {
        return ['discogs_id' => 'integer'];
    }

    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(Release::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
