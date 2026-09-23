<?php

namespace App\Data;

readonly class DigOptions
{
    public function __construct(
        public ?string $genre = null,
        public array $also = [],
        public array $exclude = [],
        public ?int $yearFrom = null,
        public ?int $yearTo = null,
        public ?string $country = null,
        public ?string $format = null,
        public ?int $minHave = null,
        public ?int $maxHave = null,
        public ?int $minWant = null,
        public string $sort = 'want_ratio',
        public int $limit = 50,
        public bool $fresh = false,
    ) {}

    public function withLimit(int $limit): self
    {
        return new self(
            genre: $this->genre,
            also: $this->also,
            exclude: $this->exclude,
            yearFrom: $this->yearFrom,
            yearTo: $this->yearTo,
            country: $this->country,
            format: $this->format,
            minHave: $this->minHave,
            maxHave: $this->maxHave,
            minWant: $this->minWant,
            sort: $this->sort,
            limit: $limit,
            fresh: $this->fresh,
        );
    }
}
