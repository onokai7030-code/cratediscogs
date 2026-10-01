<?php

namespace App\Models;

use Database\Factories\ReleaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Release extends Model
{
    /** @use HasFactory<ReleaseFactory> */
    use HasFactory;

    protected $fillable = [
        'discogs_id',
        'artist',
        'title',
        'catalog_number',
        'year',
        'country',
        'formats',
        'genres',
        'have',
        'want',
        'url',
    ];

    protected function casts(): array
    {
        return [
            'discogs_id' => 'integer',
            'year' => 'integer',
            'formats' => 'array',
            'genres' => 'array',
            'have' => 'integer',
            'want' => 'integer',
        ];
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class);
    }

    public function styles(): BelongsToMany
    {
        return $this->belongsToMany(Style::class);
    }

    public function searches(): BelongsToMany
    {
        return $this->belongsToMany(Search::class)->withPivot('position');
    }

    public function seenReleases(): HasMany
    {
        return $this->hasMany(SeenRelease::class);
    }
}
