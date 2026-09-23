<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Genre extends Model
{
    protected $fillable = ['name', 'normalized_name'];

    public function styles(): HasMany
    {
        return $this->hasMany(Style::class);
    }
}
