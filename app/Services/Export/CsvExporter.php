<?php

namespace App\Services\Export;

use App\Data\LabelMapData;
use App\Data\ReleaseData;
use RuntimeException;

class CsvExporter
{
    /** @param array<ReleaseData> $releases */
    public function releases(string $path, array $releases): void
    {
        $this->write($path, [
            ['artista', 'titolo', 'label', 'catno', 'anno', 'paese', 'formati', 'stili', 'have', 'want', 'want_ratio', 'link'],
            ...array_map(fn (ReleaseData $release): array => [
                $release->artist,
                $release->title,
                implode(', ', $release->labels),
                $release->catalogNumber,
                $release->year,
                $release->country,
                implode(', ', $release->formats),
                implode(', ', $release->styles),
                $release->have,
                $release->want,
                number_format($release->wantRatio(), 3, '.', ''),
                $release->url,
            ], $releases),
        ]);
    }

    /** @param array<LabelMapData> $labels */
    public function labels(string $path, array $labels): void
    {
        $this->write($path, [
            ['label', 'release_stile', 'attiva_da', 'attiva_a', 'want_ratio_medio', 'specializzazione', 'catalogo'],
            ...array_map(fn (LabelMapData $label): array => [
                $label->name,
                $label->styleReleaseCount,
                $label->activeFrom,
                $label->activeTo,
                number_format($label->averageWantRatio, 3, '.', ''),
                number_format($label->specialization, 4, '.', ''),
                $label->catalogSize,
            ], $labels),
        ]);
    }

    /** @param array<array<int, mixed>> $rows */
    private function write(string $path, array $rows): void
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException("Impossibile scrivere il file CSV: {$path}");
        }

        try {
            foreach ($rows as $row) {
                fputcsv($handle, array_map($this->safeCell(...), $row), escape: '');
            }
        } finally {
            fclose($handle);
        }
    }

    private function safeCell(mixed $value): string|int|float|null
    {
        if (! is_string($value) || ! preg_match('/^[=+\-@]/', $value)) {
            return $value;
        }

        return "'{$value}";
    }
}
