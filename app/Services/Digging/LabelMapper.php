<?php

namespace App\Services\Digging;

use App\Data\DigOptions;
use App\Data\LabelMapData;
use App\Data\ReleaseData;
use App\Services\Discogs\DiscogsClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LabelMapper
{
    public function __construct(
        private readonly StyleDigger $digger,
        private readonly StyleCatalog $catalog,
        private readonly DiscogsClient $discogs,
    ) {}

    /** @return array<LabelMapData> */
    public function map(
        string $style,
        DigOptions $options,
        ?int $maxReleases = null,
        ?string $labelName = null,
    ): array {
        $canonicalStyle = $this->catalog->resolveStyle($style);
        $releases = $this->digger->dig($canonicalStyle, new DigOptions(
            genre: $options->genre,
            also: $options->also,
            exclude: $options->exclude,
            yearFrom: $options->yearFrom,
            yearTo: $options->yearTo,
            country: $options->country,
            format: $options->format,
            minHave: $options->minHave,
            maxHave: $options->maxHave,
            minWant: $options->minWant,
            sort: $options->sort,
            limit: max(1, (int) config('discogs.max_results', 1000)),
            fresh: $options->fresh,
        ));

        $candidates = $this->groupByLabel($releases)
            ->when(filled($labelName), fn (Collection $labels) => $labels->filter(
                fn (Collection $items, string $name): bool => Str::contains(Str::lower($name), Str::lower(trim((string) $labelName))),
            ))
            ->sortByDesc(fn (Collection $items) => $items->count())
            ->take(max(10, $options->limit * 2));

        return $candidates
            ->map(fn (Collection $items, string $name) => $this->summarize(
                $name,
                $items,
                $canonicalStyle,
                $options->fresh,
            ))
            ->filter(fn (LabelMapData $label) => $maxReleases === null || $label->catalogSize <= $maxReleases)
            ->sort(function (LabelMapData $left, LabelMapData $right) {
                return [$right->specialization, $right->averageWantRatio, $right->styleReleaseCount]
                    <=> [$left->specialization, $left->averageWantRatio, $left->styleReleaseCount];
            })
            ->take($options->limit)
            ->values()
            ->all();
    }

    /** @param  array<ReleaseData>  $releases */
    private function groupByLabel(array $releases): Collection
    {
        return collect($releases)
            ->flatMap(fn (ReleaseData $release) => collect($release->labels)
                ->map(fn (string $label) => ['label' => $label, 'release' => $release]))
            ->groupBy('label')
            ->map(fn (Collection $rows) => $rows->pluck('release'));
    }

    private function summarize(
        string $name,
        Collection $styleReleases,
        string $targetStyle,
        bool $fresh,
    ): LabelMapData {
        $sample = $this->discogs->searchPage([
            'type' => 'release',
            'label' => $name,
        ], $fresh);
        $sampleReleases = collect($sample['results'] ?? []);
        $years = $styleReleases->pluck('year')->filter();
        $styleMatches = $sampleReleases->filter(fn (array $release) => collect($release['style'] ?? [])
            ->contains(fn (string $style) => Str::lower($style) === Str::lower($targetStyle)));

        return new LabelMapData(
            name: $name,
            styleReleaseCount: $styleReleases->count(),
            activeFrom: $years->isEmpty() ? null : (int) $years->min(),
            activeTo: $years->isEmpty() ? null : (int) $years->max(),
            averageWantRatio: (float) $styleReleases->average(
                fn (ReleaseData $release) => $release->wantRatio(),
            ),
            specialization: $sampleReleases->isEmpty() ? 0.0 : $styleMatches->count() / $sampleReleases->count(),
            catalogSize: (int) data_get($sample, 'pagination.items', $sampleReleases->count()),
        );
    }
}
