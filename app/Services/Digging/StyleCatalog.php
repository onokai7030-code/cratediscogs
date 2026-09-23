<?php

namespace App\Services\Digging;

use App\Models\Genre;
use App\Models\Style;
use Illuminate\Support\Str;

class StyleCatalog
{
    public function resolveStyle(string $name): string
    {
        $style = Style::query()
            ->where('normalized_name', $this->normalize($name))
            ->first();

        if ($style === null) {
            return trim($name);
        }

        return $style->name;
    }

    public function resolveGenre(string $name): string
    {
        $genre = Genre::query()
            ->where('normalized_name', $this->normalize($name))
            ->first();

        if ($genre === null) {
            throw new UnknownTaxonomyTerm('genere', $name, $this->suggestGenres($name));
        }

        return $genre->name;
    }

    public function suggestStyles(string $name, int $limit = 3): array
    {
        return $this->suggest($name, Style::query()->pluck('name')->all(), $limit);
    }

    public function suggestGenres(string $name, int $limit = 3): array
    {
        return $this->suggest($name, Genre::query()->pluck('name')->all(), $limit);
    }

    private function suggest(string $input, array $candidates, int $limit): array
    {
        $needle = Str::lower(Str::ascii(trim($input)));

        usort($candidates, fn (string $left, string $right) => levenshtein($needle, Str::lower(Str::ascii($left)))
            <=> levenshtein($needle, Str::lower(Str::ascii($right)))
        );

        return array_slice($candidates, 0, $limit);
    }

    private function normalize(string $value): string
    {
        return Str::lower(trim($value));
    }
}
