<?php

namespace App\Console\Commands;

use App\Data\DigOptions;
use App\Data\ReleaseData;
use App\Services\Digging\StyleDigger;
use App\Services\Digging\UnknownTaxonomyTerm;
use App\Services\Export\CsvExporter;
use App\Services\Persistence\ReleasePersister;
use Illuminate\Console\Command;
use InvalidArgumentException;

class CrateDig extends Command
{
    protected $signature = 'crate:dig
        {style : Stile Discogs principale}
        {--genre= : Genere Discogs}
        {--also=* : Stile aggiuntivo obbligatorio (ripetibile)}
        {--exclude=* : Stile da escludere (ripetibile)}
        {--years= : Intervallo, ad esempio 1994-2002}
        {--country= : Paese della release}
        {--format= : Formato, ad esempio Vinyl, 12" o LP}
        {--min-have= : Numero minimo di utenti che possiedono la release}
        {--max-have= : Numero massimo di utenti che possiedono la release}
        {--min-want= : Numero minimo di utenti che desiderano la release}
        {--sort=want_ratio : want_ratio, have, want o year}
        {--limit=50 : Numero massimo di risultati mostrati}
        {--export= : Esporta i risultati visualizzati nel file CSV indicato}
        {--hide-seen : Esclude le release già mostrate in precedenza}
        {--fresh : Ignora la cache Discogs}';

    protected $description = 'Trova release Discogs poco conosciute per genere e stile';

    public function handle(
        StyleDigger $digger,
        ReleasePersister $persister,
        CsvExporter $exporter,
    ): int {
        try {
            $options = $this->digOptions();
            $hideSeen = (bool) $this->option('hide-seen');
            $digOptions = $hideSeen
                ? $options->withLimit(max($options->limit, (int) config('discogs.max_results', 1000)))
                : $options;
            $releases = $digger->dig((string) $this->argument('style'), $digOptions);

            if ($hideSeen) {
                $releases = array_slice($persister->withoutSeen($releases), 0, $options->limit);
            }

            $persister->persistSearch('dig', (string) $this->argument('style'), $this->searchParameters(), $releases);

            $exportPath = $this->nullableString('export');

            if ($exportPath !== null) {
                $exporter->releases($exportPath, $releases);
            }
        } catch (UnknownTaxonomyTerm|InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        } catch (\Throwable $exception) {
            $this->error('Digging fallito: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($releases === []) {
            $this->warn('Nessuna release corrisponde ai filtri scelti.');

            if ($exportPath !== null) {
                $this->info("CSV esportato in: {$exportPath}");
            }

            return self::SUCCESS;
        }

        $this->table(
            ['Artista', 'Titolo', 'Label', 'Catno', 'Anno', 'Paese', 'Stili', 'Have', 'Want', 'Ratio', 'Link'],
            array_map($this->row(...), $releases),
        );

        $this->info(count($releases).' release trovate.');

        if ($exportPath !== null) {
            $this->info("CSV esportato in: {$exportPath}");
        }

        $persister->markSeen($releases);

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
            'fresh' => (bool) $this->option('fresh'),
            'hide_seen' => (bool) $this->option('hide-seen'),
        ])->reject(fn (mixed $value): bool => $value === null || $value === [] || $value === '')->all();
    }

    private function digOptions(): DigOptions
    {
        [$yearFrom, $yearTo] = $this->years((string) ($this->option('years') ?? ''));
        $sort = (string) $this->option('sort');

        if (! in_array($sort, ['want_ratio', 'have', 'want', 'year'], true)) {
            throw new InvalidArgumentException("Ordinamento '{$sort}' non valido. Usa want_ratio, have, want o year.");
        }

        return new DigOptions(
            genre: $this->nullableString('genre'),
            also: array_values($this->option('also')),
            exclude: array_values($this->option('exclude')),
            yearFrom: $yearFrom,
            yearTo: $yearTo,
            country: $this->nullableString('country'),
            format: $this->nullableString('format'),
            minHave: $this->nullableInteger('min-have'),
            maxHave: $this->nullableInteger('max-have'),
            minWant: $this->nullableInteger('min-want'),
            sort: $sort,
            limit: $this->positiveInteger('limit'),
            fresh: (bool) $this->option('fresh'),
        );
    }

    private function years(string $years): array
    {
        if ($years === '') {
            return [null, null];
        }

        if (! preg_match('/^(\d{4})-(\d{4})$/', $years, $matches)) {
            throw new InvalidArgumentException('Il filtro --years deve avere il formato YYYY-YYYY.');
        }

        $from = (int) $matches[1];
        $to = (int) $matches[2];

        if ($from > $to) {
            throw new InvalidArgumentException("L'anno iniziale non può superare quello finale.");
        }

        return [$from, $to];
    }

    private function nullableInteger(string $name): ?int
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
        $value = $this->nullableInteger($name);

        if ($value === null || $value < 1) {
            throw new InvalidArgumentException("--{$name} deve essere maggiore di zero.");
        }

        return $value;
    }

    private function nullableString(string $name): ?string
    {
        $value = trim((string) ($this->option($name) ?? ''));

        return $value === '' ? null : $value;
    }

    private function row(ReleaseData $release): array
    {
        return [
            $release->artist,
            $release->title,
            implode(', ', $release->labels),
            $release->catalogNumber ?? '—',
            $release->year ?? '—',
            $release->country ?? '—',
            implode(', ', $release->styles),
            $release->have,
            $release->want,
            number_format($release->wantRatio(), 3),
            $release->url,
        ];
    }
}
