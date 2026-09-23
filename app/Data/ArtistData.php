<?php

namespace App\Data;

readonly class ArtistData
{
    public function __construct(public int $id, public string $name) {}
}
