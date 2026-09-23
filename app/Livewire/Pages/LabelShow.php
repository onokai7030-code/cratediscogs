<?php

namespace App\Livewire\Pages;

use App\Data\LabelExploreOptions;
use App\Data\ReleaseData;
use App\Jobs\ExploreLabelSiblings;
use App\Models\LabelExploration;
use App\Services\Digging\LabelExplorer;
use App\Services\Discogs\DiscogsClient;
use App\Services\Export\CsvExporter;
use App\Services\Persistence\ReleasePersister;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class LabelShow extends Component
{
    public string $label = '';

    public ?int $yearFrom = null;

    public ?int $yearTo = null;

    public ?string $format = null;

    public string $styles = '';

    public bool $hideSeen = false;

    public bool $fresh = false;

    public ?array $details = null;

    public array $catalog = [];

    public array $videos = [];

    public ?int $explorationId = null;

    public ?string $error = null;

    public function mount(?string $label = null): void
    {
        $this->label = $label ?? (string) request()->query('label', '');
    }

    public function explore(LabelExplorer $explorer, ReleasePersister $persister): void
    {
        $validated = $this->validate([
            'label' => ['required', 'string', 'max:255'],
            'yearFrom' => ['nullable', 'integer', 'between:1900,2100'],
            'yearTo' => ['nullable', 'integer', 'between:1900,2100', 'gte:yearFrom'],
            'format' => ['nullable', 'string', 'max:100'],
            'styles' => ['nullable', 'string', 'max:500'],
        ]);
        $this->error = null;

        try {
            $data = $explorer->explore($validated['label'], new LabelExploreOptions(
                yearFrom: $validated['yearFrom'] ?? null,
                yearTo: $validated['yearTo'] ?? null,
                format: filled($validated['format'] ?? null) ? $validated['format'] : null,
                styles: collect(explode(',', $validated['styles'] ?? ''))->map(fn (string $style): string => trim($style))->filter()->values()->all(),
                fresh: $this->fresh,
            ));
            $catalog = $this->hideSeen ? $persister->withoutSeen($data->catalog) : $data->catalog;
            $persister->persistSearch('label', $validated['label'], $validated, $catalog);
            $this->details = [
                'id' => $data->label->id,
                'name' => $data->label->name,
                'parent' => $data->parent === null ? null : ['id' => $data->parent->id, 'name' => $data->parent->name],
                'sublabels' => array_map(fn ($label): array => ['id' => $label->id, 'name' => $label->name], $data->sublabels),
                'top_styles' => $data->topStyles,
            ];
            $this->catalog = array_map(fn (ReleaseData $release): array => $release->toArray(), $catalog);
        } catch (Throwable $exception) {
            report($exception);
            $this->error = $exception->getMessage();
            $this->details = null;
            $this->catalog = [];
        }
    }

    public function startSiblingSearch(): void
    {
        if ($this->details === null) {
            return;
        }

        $exploration = LabelExploration::create([
            'discogs_label_id' => $this->details['id'],
            'label_name' => $this->details['name'],
            'status' => 'pending',
            'progress' => 0,
            'message' => 'In attesa del worker',
        ]);
        ExploreLabelSiblings::dispatch($exploration->id, $this->fresh);
        $this->explorationId = $exploration->id;
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
        if ($this->catalog === []) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'crate-label-');
        $exporter->releases($path, array_map(ReleaseData::fromArray(...), $this->catalog));

        return response()->download($path, 'crate-label-catalog.csv')->deleteFileAfterSend();
    }

    public function render(): View
    {
        return view('livewire.pages.label-show', [
            'exploration' => $this->explorationId === null ? null : LabelExploration::find($this->explorationId),
        ])->title('Label · Crate');
    }
}
