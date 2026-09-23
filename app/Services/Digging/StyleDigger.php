<?php

namespace App\Services\Digging;

use App\Data\DigOptions;
use App\Data\ReleaseData;
use App\Services\Discogs\DiscogsClient;

class StyleDigger
{
    public function __construct(
        private readonly DiscogsClient $discogs,
        private readonly StyleCatalog $catalog,
        private readonly ReleaseFilter $filter,
    ) {}

    /** @return array<ReleaseData> */
    public function dig(string $style, DigOptions $options): array
    {
        $style = $this->catalog->resolveStyle($style);
        $genre = $options->genre === null ? null : $this->catalog->resolveGenre($options->genre);
        $also = array_map($this->catalog->resolveStyle(...), $options->also);
        $exclude = array_map($this->catalog->resolveStyle(...), $options->exclude);

        $requiredStyles = array_values(array_unique([$style, ...$also]));
        $parameters = array_filter([
            'type' => 'release',
            'style' => implode(', ', $requiredStyles),
            'genre' => $genre,
            'country' => $options->country,
            'format' => $options->format,
            'year' => $this->yearParameter($options),
        ], fn ($value) => $value !== null);

        $releases = collect($this->discogs->search($parameters, $options->fresh))
            ->map(ReleaseData::fromSearchResult(...))
            ->unique(fn (ReleaseData $release): int => $release->id)
            ->values()
            ->all();

        return $this->filter->apply($releases, new DigOptions(
            genre: $genre,
            also: $requiredStyles,
            exclude: $exclude,
            yearFrom: $options->yearFrom,
            yearTo: $options->yearTo,
            country: $options->country,
            format: $options->format,
            minHave: $options->minHave,
            maxHave: $options->maxHave,
            minWant: $options->minWant,
            sort: $options->sort,
            limit: $options->limit,
            fresh: $options->fresh,
        ));
    }

    private function yearParameter(DigOptions $options): ?string
    {
        if ($options->yearFrom === null || $options->yearTo === null) {
            return null;
        }

        if ($options->yearFrom === $options->yearTo) {
            return (string) $options->yearFrom;
        }

        return "{$options->yearFrom}-{$options->yearTo}";
    }
}
