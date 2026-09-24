<?php

namespace App\Data;

use Illuminate\Support\Str;

readonly class ReleaseData
{
    public function __construct(
        public int $id,
        public string $artist,
        public string $title,
        public array $labels,
        public ?string $catalogNumber,
        public ?int $year,
        public ?string $country,
        public array $formats,
        public array $genres,
        public array $styles,
        public int $have,
        public int $want,
        public string $url,
        public ?string $imageUrl = null,
    ) {}

    public static function fromSearchResult(array $result): self
    {
        [$artist, $title] = self::splitTitle((string) ($result['title'] ?? 'Sconosciuto'));

        return new self(
            id: (int) ($result['id'] ?? 0),
            artist: $artist,
            title: $title,
            labels: self::uniqueStrings($result['label'] ?? []),
            catalogNumber: filled($result['catno'] ?? null) ? (string) $result['catno'] : null,
            year: self::year($result['year'] ?? null),
            country: filled($result['country'] ?? null) ? (string) $result['country'] : null,
            formats: self::formats($result['format'] ?? []),
            genres: self::uniqueStrings($result['genre'] ?? []),
            styles: self::uniqueStrings($result['style'] ?? []),
            have: (int) data_get($result, 'community.have', 0),
            want: (int) data_get($result, 'community.want', 0),
            url: 'https://www.discogs.com'.($result['uri'] ?? "/release/{$result['id']}"),
            imageUrl: self::imageUrl($result),
        );
    }

    public function wantRatio(): float
    {
        return $this->want / max($this->have, 1);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'artist' => $this->artist,
            'title' => $this->title,
            'labels' => $this->labels,
            'catalog_number' => $this->catalogNumber,
            'year' => $this->year,
            'country' => $this->country,
            'formats' => $this->formats,
            'genres' => $this->genres,
            'styles' => $this->styles,
            'have' => $this->have,
            'want' => $this->want,
            'want_ratio' => $this->wantRatio(),
            'url' => $this->url,
            'image_url' => $this->imageUrl,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) $data['id'],
            artist: (string) $data['artist'],
            title: (string) $data['title'],
            labels: (array) $data['labels'],
            catalogNumber: $data['catalog_number'] === null ? null : (string) $data['catalog_number'],
            year: $data['year'] === null ? null : (int) $data['year'],
            country: $data['country'] === null ? null : (string) $data['country'],
            formats: (array) $data['formats'],
            genres: (array) $data['genres'],
            styles: (array) $data['styles'],
            have: (int) $data['have'],
            want: (int) $data['want'],
            url: (string) $data['url'],
            imageUrl: filled($data['image_url'] ?? null) ? (string) $data['image_url'] : null,
        );
    }

    /** @param array<string, mixed> $result */
    private static function imageUrl(array $result): ?string
    {
        $imageUrl = $result['cover_image'] ?? $result['thumb'] ?? null;

        if (! is_string($imageUrl) || ! str_starts_with($imageUrl, 'https://')) {
            return null;
        }

        return $imageUrl;
    }

    private static function splitTitle(string $combined): array
    {
        if (! str_contains($combined, ' - ')) {
            return ['Sconosciuto', $combined];
        }

        return explode(' - ', $combined, 2);
    }

    private static function formats(array $formats): array
    {
        return collect($formats)
            ->flatMap(fn ($format) => is_array($format)
                ? [(string) ($format['name'] ?? ''), ...($format['descriptions'] ?? [])]
                : [(string) $format])
            ->filter()
            ->unique(fn (string $format) => Str::lower($format))
            ->values()
            ->all();
    }

    private static function uniqueStrings(array $values): array
    {
        return collect($values)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (string) $value)
            ->unique(fn (string $value) => Str::lower($value))
            ->values()
            ->all();
    }

    private static function year(mixed $year): ?int
    {
        $year = (int) $year;

        return $year > 0 ? $year : null;
    }
}
