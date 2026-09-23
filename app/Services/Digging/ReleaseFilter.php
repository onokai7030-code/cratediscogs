<?php

namespace App\Services\Digging;

use App\Data\DigOptions;
use App\Data\ReleaseData;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReleaseFilter
{
    /** @param  array<ReleaseData>  $releases */
    public function apply(array $releases, DigOptions $options): array
    {
        return collect($releases)
            ->filter(fn (ReleaseData $release) => $this->matches($release, $options))
            ->sortByDesc(fn (ReleaseData $release) => $this->sortValue($release, $options->sort))
            ->take(max(1, $options->limit))
            ->values()
            ->all();
    }

    private function matches(ReleaseData $release, DigOptions $options): bool
    {
        $styles = $this->normalize($release->styles);
        $also = $this->normalize($options->also);
        $excluded = $this->normalize($options->exclude);

        return collect($also)->every(fn (string $style) => in_array($style, $styles, true))
            && collect($excluded)->every(fn (string $style) => ! in_array($style, $styles, true))
            && ($options->yearFrom === null || ($release->year !== null && $release->year >= $options->yearFrom))
            && ($options->yearTo === null || ($release->year !== null && $release->year <= $options->yearTo))
            && ($options->country === null || Str::lower((string) $release->country) === Str::lower($options->country))
            && ($options->format === null || in_array(Str::lower($options->format), $this->normalize($release->formats), true))
            && ($options->minHave === null || $release->have >= $options->minHave)
            && ($options->maxHave === null || $release->have <= $options->maxHave)
            && ($options->minWant === null || $release->want >= $options->minWant);
    }

    private function sortValue(ReleaseData $release, string $sort): float|int
    {
        return match ($sort) {
            'have' => $release->have,
            'want' => $release->want,
            'year' => $release->year ?? 0,
            default => $release->wantRatio(),
        };
    }

    private function normalize(array $values): array
    {
        return Collection::make($values)
            ->map(fn (string $value) => Str::lower(trim($value)))
            ->all();
    }
}
