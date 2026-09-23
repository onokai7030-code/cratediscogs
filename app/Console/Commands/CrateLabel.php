<?php

namespace App\Console\Commands;

use App\Data\LabelExploreOptions;
use App\Data\ReleaseData;
use App\Jobs\ExploreLabelSiblings;
use App\Models\LabelExploration;
use App\Services\Digging\LabelExplorer;
use App\Services\Export\CsvExporter;
use App\Services\Persistence\ReleasePersister;
use Illuminate\Console\Command;
use InvalidArgumentException;

class CrateLabel extends Command
{
    protected $signature = 'crate:label
        {label : Nome, ID o URL Discogs della label}
        {--depth=1 : Profondità 1 o 2}
        {--years= : Intervallo YYYY-YYYY}
        {--format= : Formato del catalogo}
        {--style=* : Stile obbligatorio nel catalogo (ripetibile)}
        {--export= : Esporta il catalogo visualizzato nel file CSV indicato}
        {--hide-seen : Esclude le release già mostrate in precedenza}
        {--fresh : Ignora la cache Discogs}';

    protected $description = 'Esplora relazioni, catalogo e stili di una label Discogs';

    public function handle(
        LabelExplorer $explorer,
        ReleasePersister $persister,
        CsvExporter $exporter,
    ): int {
        try {
            $depth = (int) $this->option('depth');

            if (! in_array($depth, [1, 2], true)) {
                throw new InvalidArgumentException('--depth deve essere 1 oppure 2.');
            }

            [$from, $to] = $this->years((string) ($this->option('years') ?? ''));
            $data = $explorer->explore((string) $this->argument('label'), new LabelExploreOptions(
                yearFrom: $from,
                yearTo: $to,
                format: $this->textOption('format'),
                styles: array_values($this->option('style')),
                fresh: (bool) $this->option('fresh'),
            ));
            $persister->persistSearch('label', (string) $this->argument('label'), $this->searchParameters(), $data->catalog);
            $catalog = (bool) $this->option('hide-seen')
                ? $persister->withoutSeen($data->catalog)
                : $data->catalog;
            $exportPath = $this->textOption('export');

            if ($exportPath !== null) {
                $exporter->releases($exportPath, $catalog);
            }
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        } catch (\Throwable $exception) {
            $this->error('Esplorazione label fallita: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("{$data->label->name} [Discogs #{$data->label->id}]");
        $this->line('Label madre: '.($data->parent?->name ?? '—'));
        $this->line('Sub-label: '.($data->sublabels === []
            ? '—'
            : collect($data->sublabels)->pluck('name')->implode(', ')));
        $this->line('Stili principali: '.($data->topStyles === []
            ? '—'
            : collect($data->topStyles)->map(fn (int $count, string $style) => "{$style} ({$count})")->implode(', ')));

        if ($catalog === []) {
            $this->warn('Nessuna release del catalogo corrisponde ai filtri.');
        } else {
            $this->table(
                ['Artista', 'Titolo', 'Label', 'Catno', 'Anno', 'Paese', 'Formato', 'Stili', 'Have', 'Want', 'Ratio', 'Link'],
                array_map($this->releaseRow(...), $catalog),
            );
            $this->info(count($catalog).' release nel catalogo filtrato.');
            $persister->markSeen($catalog);
        }

        if ($exportPath !== null) {
            $this->info("CSV esportato in: {$exportPath}");
        }

        if ($depth === 2) {
            $exploration = LabelExploration::create([
                'discogs_label_id' => $data->label->id,
                'label_name' => $data->label->name,
                'status' => 'pending',
                'progress' => 0,
                'message' => 'In attesa del worker',
            ]);

            ExploreLabelSiblings::dispatch($exploration->id, (bool) $this->option('fresh'));

            $this->newLine();
            $this->info("Job label sorelle accodato: #{$exploration->id}");
            $this->line('Avvia il worker: php artisan queue:work');
            $this->line("Controlla l'avanzamento: php artisan crate:label:status {$exploration->id}");
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function searchParameters(): array
    {
        return collect([
            'depth' => $this->option('depth'),
            'years' => $this->option('years'),
            'format' => $this->option('format'),
            'styles' => $this->option('style'),
            'fresh' => (bool) $this->option('fresh'),
            'hide_seen' => (bool) $this->option('hide-seen'),
        ])->reject(fn (mixed $value): bool => $value === null || $value === [] || $value === '')->all();
    }

    private function years(string $years): array
    {
        if ($years === '') {
            return [null, null];
        }

        if (! preg_match('/^(\d{4})-(\d{4})$/', $years, $matches) || (int) $matches[1] > (int) $matches[2]) {
            throw new InvalidArgumentException('Il filtro --years deve essere un intervallo YYYY-YYYY valido.');
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    private function textOption(string $name): ?string
    {
        $value = trim((string) ($this->option($name) ?? ''));

        return $value === '' ? null : $value;
    }

    private function releaseRow(ReleaseData $release): array
    {
        return [
            $release->artist,
            $release->title,
            implode(', ', $release->labels),
            $release->catalogNumber ?? '—',
            $release->year ?? '—',
            $release->country ?? '—',
            implode(', ', $release->formats),
            implode(', ', $release->styles),
            $release->have,
            $release->want,
            number_format($release->wantRatio(), 3),
            $release->url,
        ];
    }
}
