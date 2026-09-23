<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class DiscogsTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/discogs_genres_styles.csv');
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Impossibile leggere {$path}");
        }

        fgetcsv($handle, escape: '');

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            [$genreName, $styleName] = $row;
            $genre = Genre::firstOrCreate(
                ['name' => $genreName],
                ['normalized_name' => Str::lower(trim($genreName))],
            );

            $genre->styles()->firstOrCreate(
                ['name' => $styleName],
                ['normalized_name' => Str::lower(trim($styleName))],
            );
        }

        fclose($handle);
    }
}
