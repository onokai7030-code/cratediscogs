<?php

namespace App\Services\Digging;

use App\Data\LabelData;
use App\Data\LabelExplorationData;
use App\Data\LabelExploreOptions;
use App\Data\ReleaseData;
use App\Services\Discogs\DiscogsClient;
use Illuminate\Support\Str;
use InvalidArgumentException;

class LabelExplorer
{
    public function __construct(private readonly DiscogsClient $discogs) {}

    public function resolve(string $input, bool $fresh = false): LabelData
    {
        $input = trim($input);

        if (ctype_digit($input)) {
            $payload = $this->discogs->label((int) $input, $fresh);

            return new LabelData((int) $payload['id'], (string) $payload['name']);
        }

        if (preg_match('~discogs\.com/(?:[a-z]{2}/)?label/(\d+)~i', $input, $matches)) {
            return $this->resolve($matches[1], $fresh);
        }

        $payload = $this->discogs->searchPage([
            'type' => 'label',
            'q' => $input,
        ], $fresh);
        $results = collect($payload['results'] ?? []);
        $match = $results->first(fn (array $label) => Str::lower((string) ($label['title'] ?? '')) === Str::lower($input))
            ?? $results->first();

        if ($match === null) {
            throw new InvalidArgumentException("Label '{$input}' non trovata su Discogs.");
        }

        return $this->resolve((string) $match['id'], $fresh);
    }

    public function explore(string|LabelData $label, LabelExploreOptions $options): LabelExplorationData
    {
        $label = is_string($label) ? $this->resolve($label, $options->fresh) : $label;
        $details = $this->discogs->label($label->id, $options->fresh);
        $catalogPayload = $this->discogs->search([
            'type' => 'release',
            'label' => $label->name,
        ], $options->fresh);
        $unfilteredCatalog = array_map(ReleaseData::fromSearchResult(...), $catalogPayload);
        $catalog = array_values(array_filter(
            $unfilteredCatalog,
            fn (ReleaseData $release) => $this->matches($release, $options),
        ));

        return new LabelExplorationData(
            label: new LabelData((int) $details['id'], (string) $details['name']),
            parent: $this->relatedLabel($details['parent_label'] ?? null),
            sublabels: collect($details['sublabels'] ?? [])->map($this->relatedLabel(...))->filter()->all(),
            catalog: $catalog,
            topStyles: $this->topStyles($unfilteredCatalog),
        );
    }

    /**
     * @param  callable(int, int, string): void  $progress
     * @return array<int, array{name: string, shared_artists: int}>
     */
    public function findSiblings(LabelData $label, callable $progress, bool $fresh = false): array
    {
        $catalog = $this->discogs->search([
            'type' => 'release',
            'label' => $label->name,
        ], $fresh);
        $releaseSample = array_slice($catalog, 0, 25);
        $artists = [];
        $completed = 0;
        $total = max(1, count($releaseSample));

        foreach ($releaseSample as $release) {
            $details = $this->discogs->release((int) $release['id'], $fresh);

            foreach ($details['artists'] ?? [] as $artist) {
                if (isset($artist['id'], $artist['name'])) {
                    $artists[(int) $artist['id']] = (string) $artist['name'];
                }
            }

            $completed++;
            $progress($completed, $total + count($artists), 'Raccolta degli artisti della label');
        }

        $total += count($artists);
        $shared = [];

        foreach ($artists as $artistId => $artistName) {
            $artistLabels = collect($this->discogs->artistReleases($artistId, fresh: $fresh))
                ->flatMap(fn (array $release) => is_array($release['label'] ?? null)
                    ? $release['label']
                    : [(string) ($release['label'] ?? '')])
                ->filter()
                ->unique(fn (string $name) => Str::lower($name));

            foreach ($artistLabels as $otherLabel) {
                if (Str::lower($otherLabel) === Str::lower($label->name)) {
                    continue;
                }

                $shared[$otherLabel][$artistId] = $artistName;
            }

            $completed++;
            $progress($completed, $total, "Catalogo artista: {$artistName}");
        }

        return collect($shared)
            ->map(fn (array $sharedArtists, string $name) => [
                'name' => $name,
                'shared_artists' => count($sharedArtists),
            ])
            ->sortByDesc('shared_artists')
            ->values()
            ->all();
    }

    private function matches(ReleaseData $release, LabelExploreOptions $options): bool
    {
        $styles = collect($release->styles)->map(fn (string $style) => Str::lower($style));

        return ($options->yearFrom === null || ($release->year !== null && $release->year >= $options->yearFrom))
            && ($options->yearTo === null || ($release->year !== null && $release->year <= $options->yearTo))
            && ($options->format === null || collect($release->formats)->contains(
                fn (string $format) => Str::lower($format) === Str::lower($options->format),
            ))
            && collect($options->styles)->every(
                fn (string $style) => $styles->contains(Str::lower($style)),
            );
    }

    /** @param  array<ReleaseData>  $catalog */
    private function topStyles(array $catalog): array
    {
        return collect($catalog)
            ->flatMap(fn (ReleaseData $release) => $release->styles)
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->all();
    }

    private function relatedLabel(?array $label): ?LabelData
    {
        if ($label === null || ! isset($label['id'], $label['name'])) {
            return null;
        }

        return new LabelData((int) $label['id'], (string) $label['name']);
    }
}
