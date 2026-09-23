<?php

namespace App\Livewire\Pages;

use App\Data\DigOptions;
use App\Data\LabelMapData;
use App\Services\Digging\LabelMapper;
use App\Services\Digging\StyleCatalog;
use App\Services\Digging\UnknownTaxonomyTerm;
use App\Services\Export\CsvExporter;
use App\Services\Persistence\ReleasePersister;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class Labels extends Component
{
    public string $style = '';

    public string $labelName = '';

    public string $also = '';

    public string $exclude = '';

    public int $limit = 15;

    public ?int $maxReleases = null;

    public bool $fresh = false;

    public array $labels = [];

    public ?string $error = null;

    public bool $hasSearched = false;

    public function mount(): void
    {
        $this->style = (string) request()->query('style', '');
    }

    public function search(
        LabelMapper $mapper,
        StyleCatalog $catalog,
        ReleasePersister $persister,
    ): void {
        $validated = $this->validate([
            'style' => ['required', 'string', 'max:100'],
            'labelName' => ['nullable', 'string', 'max:255'],
            'also' => ['nullable', 'string', 'max:500'],
            'exclude' => ['nullable', 'string', 'max:500'],
            'limit' => ['required', 'integer', 'between:1,50'],
            'maxReleases' => ['nullable', 'integer', 'min:1'],
        ]);
        $this->error = null;
        $this->hasSearched = true;

        try {
            $this->style = $catalog->resolveStyle($validated['style']);
            $this->labelName = filled($validated['labelName'] ?? null)
                ? Str::title(trim($validated['labelName']))
                : '';
            $also = array_map($catalog->resolveStyle(...), $this->terms($validated['also'] ?? ''));
            $exclude = array_map($catalog->resolveStyle(...), $this->terms($validated['exclude'] ?? ''));
            $this->also = implode(', ', $also);
            $this->exclude = implode(', ', $exclude);
            $labels = $mapper->map(
                $this->style,
                new DigOptions(also: $also, exclude: $exclude, limit: $validated['limit'], fresh: $this->fresh),
                $validated['maxReleases'] ?? null,
                filled($this->labelName) ? $this->labelName : null,
            );
            $this->labels = array_map(fn (LabelMapData $label): array => get_object_vars($label), $labels);
            $exactLabel = collect($this->labels)->first(
                fn (array $label): bool => strcasecmp($label['name'], trim($this->labelName)) === 0,
            );

            if ($exactLabel !== null) {
                $this->labelName = $exactLabel['name'];
            }

            $persister->recordSearch('labels', $this->style, $validated, count($labels));
        } catch (UnknownTaxonomyTerm|InvalidArgumentException $exception) {
            $this->error = $exception->getMessage();
            $this->labels = [];
        } catch (Throwable $exception) {
            report($exception);
            $this->error = $exception->getMessage();
            $this->labels = [];
        }
    }

    public function export(CsvExporter $exporter): ?BinaryFileResponse
    {
        if ($this->labels === []) {
            return null;
        }

        $labels = array_map(fn (array $label): LabelMapData => new LabelMapData(...$label), $this->labels);
        $path = tempnam(sys_get_temp_dir(), 'crate-labels-');
        $exporter->labels($path, $labels);

        return response()->download($path, 'crate-labels.csv')->deleteFileAfterSend();
    }

    public function render(): View
    {
        return view('livewire.pages.labels')->title('Labels · Crate');
    }

    private function terms(string $value): array
    {
        return collect(explode(',', $value))->map(fn (string $term): string => trim($term))->filter()->values()->all();
    }
}
