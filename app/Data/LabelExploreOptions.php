<?php

namespace App\Data;

readonly class LabelExploreOptions
{
    public function __construct(
        public ?int $yearFrom = null,
        public ?int $yearTo = null,
        public ?string $format = null,
        public array $styles = [],
        public bool $fresh = false,
    ) {}
}
