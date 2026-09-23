<?php

namespace App\Services\Persistence;

use App\Data\ReleaseData;
use App\Models\Label;
use App\Models\Release;
use App\Models\Search;
use App\Models\SeenRelease;
use App\Models\Style;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReleasePersister
{
    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<ReleaseData>  $releases
     */
    public function persistSearch(string $type, string $query, array $parameters, array $releases): Search
    {
        $uniqueReleases = collect($releases)
            ->unique(fn (ReleaseData $release): int => $release->id)
            ->values();

        return DB::transaction(function () use ($type, $query, $parameters, $uniqueReleases): Search {
            $search = Search::create([
                'type' => $type,
                'query' => $query,
                'parameters' => $parameters,
                'result_count' => $uniqueReleases->count(),
            ]);

            foreach ($uniqueReleases as $position => $releaseData) {
                $release = $this->persistRelease($releaseData);
                $search->releases()->attach($release, ['position' => $position + 1]);
            }

            return $search;
        });
    }

    /** @param array<string, mixed> $parameters */
    public function recordSearch(string $type, string $query, array $parameters, int $resultCount): Search
    {
        return Search::create([
            'type' => $type,
            'query' => $query,
            'parameters' => $parameters,
            'result_count' => $resultCount,
        ]);
    }

    /**
     * @param  array<ReleaseData>  $releases
     * @return array<ReleaseData>
     */
    public function withoutSeen(array $releases): array
    {
        $seenDiscogsIds = Release::query()
            ->whereIn('discogs_id', array_column($releases, 'id'))
            ->whereHas('seen')
            ->pluck('discogs_id')
            ->all();

        return array_values(array_filter(
            $releases,
            fn (ReleaseData $release): bool => ! in_array($release->id, $seenDiscogsIds, true),
        ));
    }

    /** @param array<ReleaseData> $releases */
    public function markSeen(array $releases): void
    {
        if ($releases === []) {
            return;
        }

        $releaseIds = Release::query()
            ->whereIn('discogs_id', array_column($releases, 'id'))
            ->pluck('id');

        foreach ($releaseIds as $releaseId) {
            $seenRelease = SeenRelease::query()->firstOrNew(['release_id' => $releaseId]);
            $seenRelease->first_seen_at ??= now();
            $seenRelease->last_seen_at = now();
            $seenRelease->save();
        }
    }

    public function markDiscogsReleaseSeen(int $discogsId): void
    {
        $release = Release::query()->where('discogs_id', $discogsId)->firstOrFail();
        $seenRelease = SeenRelease::query()->firstOrNew(['release_id' => $release->id]);
        $seenRelease->first_seen_at ??= now();
        $seenRelease->last_seen_at = now();
        $seenRelease->save();
    }

    private function persistRelease(ReleaseData $data): Release
    {
        $release = Release::query()->updateOrCreate(
            ['discogs_id' => $data->id],
            [
                'artist' => $data->artist,
                'title' => $data->title,
                'catalog_number' => $data->catalogNumber,
                'year' => $data->year,
                'country' => $data->country,
                'formats' => $data->formats,
                'genres' => $data->genres,
                'have' => $data->have,
                'want' => $data->want,
                'url' => $data->url,
            ],
        );

        $labelIds = collect($data->labels)
            ->map(fn (string $name): int => Label::query()->firstOrCreate(
                ['normalized_name' => Str::lower(trim($name))],
                ['name' => $name],
            )->id)
            ->all();
        $release->labels()->sync($labelIds);

        $styleIds = Style::query()
            ->whereIn('normalized_name', collect($data->styles)->map(fn (string $style) => Str::lower(trim($style))))
            ->pluck('id')
            ->all();
        $release->styles()->sync($styleIds);

        return $release;
    }
}
