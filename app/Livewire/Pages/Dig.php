<?php

namespace App\Livewire\Pages;

use App\Data\DigOptions;
use App\Data\ReleaseData;
use App\Services\Digging\ReleaseFilter;
use App\Services\Digging\StyleCatalog;
use App\Services\Digging\StyleDigger;
use App\Services\Digging\UnknownTaxonomyTerm;
use App\Services\Discogs\DiscogsClient;
use App\Services\Export\CsvExporter;
use App\Services\Persistence\ReleasePersister;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class Dig extends Component
{
    public string $style = '';

    public ?string $genre = null;

    public string $also = '';

    public string $styleMatch = 'any';

    public string $exclude = '';

    public ?int $yearFrom = null;

    public ?int $yearTo = null;

    public ?string $country = null;

    public ?string $format = null;

    public ?int $minHave = null;

    public ?int $maxHave = null;

    public ?int $minWant = null;

    public string $sort = 'want_ratio';

    public int $limit = 50;

    public bool $hideSeen = false;

    public bool $fresh = false;

    public array $results = [];

    public array $videos = [];

    public ?string $error = null;

    public bool $hasSearched = false;

    public int $page = 1;

    public int $perPage = 15;

    public function mount(): void
    {
        $this->style = (string) request()->query('style', '');
    }

    public function search(
        StyleDigger $digger,
        StyleCatalog $catalog,
        ReleaseFilter $filter,
        ReleasePersister $persister,
    ): void {
        $validated = $this->validate($this->rules());
        $this->error = null;
        $this->hasSearched = true;

        try {
            $this->style = $catalog->resolveStyle($validated['style']);
            $this->genre = filled($validated['genre'] ?? null) ? $catalog->resolveGenre($validated['genre']) : null;
            $also = array_map($catalog->resolveStyle(...), $this->terms($validated['also'] ?? ''));
            $exclude = array_map($catalog->resolveStyle(...), $this->terms($validated['exclude'] ?? ''));
            $this->also = implode(', ', $also);
            $this->exclude = implode(', ', $exclude);
            $this->country = filled($validated['country'] ?? null) ? mb_strtoupper(trim($validated['country'])) : null;
            $this->format = filled($validated['format'] ?? null) ? ucfirst(mb_strtolower(trim($validated['format']))) : null;
            $options = new DigOptions(
                genre: $this->genre,
                also: $also,
                exclude: $exclude,
                yearFrom: $validated['yearFrom'] ?? null,
                yearTo: $validated['yearTo'] ?? null,
                country: $this->country,
                format: $this->format,
                minHave: $validated['minHave'] ?? null,
                maxHave: $validated['maxHave'] ?? null,
                minWant: $validated['minWant'] ?? null,
                sort: $validated['sort'],
                limit: $this->hideSeen ? max($validated['limit'], (int) config('discogs.max_results', 1000)) : $validated['limit'],
                fresh: $this->fresh,
            );
            if ($validated['styleMatch'] === 'any' && $also !== []) {
                $selectedStyles = array_values(array_unique([$this->style, ...$also]));
                $searchOptions = new DigOptions(
                    genre: $options->genre,
                    exclude: $options->exclude,
                    yearFrom: $options->yearFrom,
                    yearTo: $options->yearTo,
                    country: $options->country,
                    format: $options->format,
                    minHave: $options->minHave,
                    maxHave: $options->maxHave,
                    minWant: $options->minWant,
                    sort: $options->sort,
                    limit: max($options->limit, (int) config('discogs.max_results', 1000)),
                    fresh: $options->fresh,
                );
                $releases = collect($selectedStyles)
                    ->flatMap(fn (string $style): array => $digger->dig($style, $searchOptions))
                    ->unique(fn (ReleaseData $release): int => $release->id)
                    ->values()
                    ->all();
                $releases = $filter->apply($releases, $searchOptions);
                $releases = collect($releases)
                    ->sortByDesc(fn (ReleaseData $release): int => $this->matchedStyleCount($release, $selectedStyles))
                    ->take($validated['limit'])
                    ->values()
                    ->all();
            } else {
                $releases = $digger->dig($this->style, $options);
            }

            if ($this->hideSeen) {
                $releases = array_slice($persister->withoutSeen($releases), 0, $validated['limit']);
            }

            $persister->persistSearch('dig', $this->style, $validated, $releases);
            $this->results = array_map(fn (ReleaseData $release): array => $release->toArray(), $releases);
            $this->page = 1;
        } catch (UnknownTaxonomyTerm|InvalidArgumentException $exception) {
            $this->error = $exception->getMessage();
            $this->results = [];
        } catch (Throwable $exception) {
            if (app()->environment('testing')) {
                throw $exception;
            }

            report($exception);
            $this->error = $exception->getMessage();
            $this->results = [];
        }
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, ['artist', 'title', 'year', 'have', 'want', 'want_ratio'], true)) {
            return;
        }

        $this->sort = $column;
        usort($this->results, fn (array $left, array $right): int => $right[$column] <=> $left[$column]);
        $this->page = 1;
    }

    public function markSeen(int $discogsId, ReleasePersister $persister): void
    {
        $persister->markDiscogsReleaseSeen($discogsId);
        session()->flash('status', 'Release segnata come vista.');
    }

    public function loadVideos(int $discogsId, DiscogsClient $discogs): void
    {
        $release = $discogs->release($discogsId);
        $this->videos[$discogsId] = collect($release['videos'] ?? [])->pluck('uri')->filter()->values()->all();
    }

    public function export(CsvExporter $exporter): ?BinaryFileResponse
    {
        if ($this->results === []) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'crate-dig-');
        $exporter->releases($path, array_map(ReleaseData::fromArray(...), $this->results));

        return response()->download($path, 'crate-dig.csv')->deleteFileAfterSend();
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function nextPage(): void
    {
        $this->page = min($this->pageCount(), $this->page + 1);
    }

    public function render(): View
    {
        return view('livewire.pages.dig', [
            'visibleResults' => array_slice($this->results, ($this->page - 1) * $this->perPage, $this->perPage),
            'pageCount' => $this->pageCount(),
        ])->title('Dig · Crate');
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'style' => ['required', 'string', 'max:100'],
            'genre' => ['nullable', 'string', 'max:100'],
            'also' => ['nullable', 'string', 'max:500'],
            'styleMatch' => ['required', Rule::in(['any', 'all'])],
            'exclude' => ['nullable', 'string', 'max:500'],
            'yearFrom' => ['nullable', 'integer', 'between:1900,2100'],
            'yearTo' => ['nullable', 'integer', 'between:1900,2100', 'gte:yearFrom'],
            'country' => ['nullable', 'string', 'max:100'],
            'format' => ['nullable', 'string', 'max:100'],
            'minHave' => ['nullable', 'integer', 'min:0'],
            'maxHave' => ['nullable', 'integer', 'min:0'],
            'minWant' => ['nullable', 'integer', 'min:0'],
            'sort' => ['required', Rule::in(['want_ratio', 'have', 'want', 'year'])],
            'limit' => ['required', 'integer', 'between:1,250'],
        ];
    }

    private function pageCount(): int
    {
        return max(1, (int) ceil(count($this->results) / $this->perPage));
    }

    private function terms(string $value): array
    {
        return collect(explode(',', $value))->map(fn (string $term): string => trim($term))->filter()->values()->all();
    }

    /** @param array<string> $selectedStyles */
    private function matchedStyleCount(ReleaseData $release, array $selectedStyles): int
    {
        $releaseStyles = array_map(fn (string $style): string => mb_strtolower($style), $release->styles);

        return collect($selectedStyles)
            ->filter(fn (string $style): bool => in_array(mb_strtolower($style), $releaseStyles, true))
            ->count();
    }
}
