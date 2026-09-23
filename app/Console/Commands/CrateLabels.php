<?php

namespace App\Console\Commands;

use App\Data\DigOptions;
use App\Data\LabelMapData;
use App\Services\Digging\LabelMapper;
use App\Services\Digging\UnknownTaxonomyTerm;
use App\Services\Export\CsvExporter;
use App\Services\Persistence\ReleasePersister;
use Illuminate\Console\Command;
use InvalidArgumentException;

class CrateLabels extends Command
{
    protected $signature = 'crate:labels
        {style : Stile Discogs principale}
        {--genre= : Genere Discogs}
        {--also=* : Stile aggiuntivo obbligatorio (ripetibile)}
        {--exclude=* : Stile da escludere (ripetibile)}
        {--years= : Intervallo, ad esempio 1994-2002}
        {--country= : Paese della release}
        {--format= : Formato, ad esempio Vinyl, 12" o LP}
        {--min-have= : Have minimo}
        {--max-have= : Have massimo}
        {--min-want= : Want minimo}
        {--sort=want_ratio : want_ratio, have, want o year}
        {--limit=10 : Numero massimo di label}
        {--max-releases= : Esclude label con un catalogo più grande}
        {--export= : Esporta le label visualizzate nel file CSV indicato}
        {--fresh : Ignora la cache Discogs}';

    protected $description = 'Mappa le etichette più specializzate in uno stile Discogs';

    public function handle(
        LabelMapper $mapper,
        ReleasePersister $persister,
        CsvExporter $exporter,
    ): int {
        try {
            [$from, $to] = $this->years((string) ($this->option('years') ?? ''));
            $sort = (string) $this->option('sort');

            if (! in_array($sort, ['want_ratio', 'have', 'want', 'year'], true)) {
                throw new InvalidArgumentException("Ordinamento '{$sort}' non valido.");
            }

            $options = new DigOptions(
                genre: $this->textOption('genre'),
                also: array_values($this->option('also')),
                exclude: array_values($this->option('exclude')),
                yearFrom: $from,
                yearTo: $to,
                country: $this->textOption('country'),
                format: $this->textOption('format'),
                minHave: $this->integerOption('min-have'),
                maxHave: $this->integerOption('max-have'),
                minWant: $this->integerOption('min-want'),
                sort: $sort,
                limit: $this->positiveInteger('limit'),
                fresh: (bool) $this->option('fresh'),
            );

            $labels = $mapper->map(
                (string) $this->argument('style'),
                $options,
                $this->integerOption('max-releases'),
            );
            $persister->recordSearch('labels', (string) $this->argument('style'), $this->searchParameters(), count($labels));

            $exportPath = $this->textOption('export');

            if ($exportPath !== null) {
                $exporter->labels($exportPath, $labels);
            }
        } catch (UnknownTaxonomyTerm|InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        } catch (\Throwable $exception) {
            $this->error('Mappatura label fallita: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($labels === []) {
            $this->warn('Nessuna label corrisponde ai filtri scelti.');

            if ($exportPath !== null) {
                $this->info("CSV esportato in: {$exportPath}");
            }

            return self::SUCCESS;
        }

        $this->table(
            ['Label', 'Release stile', 'Anni', 'Want ratio medio', 'Specializzazione', 'Catalogo'],
            array_map($this->row(...), $labels),
        );
        $this->info(count($labels).' label trovate.');

        if ($exportPath !== null) {
            $this->info("CSV esportato in: {$exportPath}");
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function searchParameters(): array
    {
        return collect([
            'genre' => $this->option('genre'),
            'also' => $this->option('also'),
            'exclude' => $this->option('exclude'),
            'years' => $this->option('years'),
            'country' => $this->option('country'),
            'format' => $this->option('format'),
            'min_have' => $this->option('min-have'),
            'max_have' => $this->option('max-have'),
            'min_want' => $this->option('min-want'),
            'sort' => $this->option('sort'),
            'limit' => $this->option('limit'),
            'max_releases' => $this->option('max-releases'),
            'fresh' => (bool) $this->option('fresh'),
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

    private function integerOption(string $name): ?int
    {
        $value = $this->option($name);

        if ($value === null) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) {
            throw new InvalidArgumentException("--{$name} deve essere un intero non negativo.");
        }

        return (int) $value;
    }

    private function positiveInteger(string $name): int
    {
        $value = $this->integerOption($name);

        if ($value === null || $value < 1) {
            throw new InvalidArgumentException("--{$name} deve essere maggiore di zero.");
        }

        return $value;
    }

    private function textOption(string $name): ?string
    {
        $value = trim((string) ($this->option($name) ?? ''));

        return $value === '' ? null : $value;
    }

    private function row(LabelMapData $label): array
    {
        $years = $label->activeFrom === null
            ? '—'
            : ($label->activeFrom === $label->activeTo ? (string) $label->activeFrom : "{$label->activeFrom}–{$label->activeTo}");

        return [
            $label->name,
            $label->styleReleaseCount,
            $years,
            number_format($label->averageWantRatio, 3),
            number_format($label->specialization * 100, 1).'%',
            $label->catalogSize,
        ];
    }
}
