<?php

use App\Data\DigOptions;
use App\Data\ReleaseData;
use App\Models\Genre;
use App\Models\Style;
use App\Services\Digging\ReleaseFilter;
use App\Services\Digging\StyleDigger;
use Database\Seeders\DiscogsTaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set([
        'cache.default' => 'array',
        'discogs.token' => 'test-token',
        'discogs.max_results' => 100,
    ]);

    $genre = Genre::create(['name' => 'Electronic', 'normalized_name' => 'electronic']);

    foreach (['Deep House', 'Dub Techno', 'Ambient', 'Tech House'] as $style) {
        Style::create([
            'genre_id' => $genre->id,
            'name' => $style,
            'normalized_name' => mb_strtolower($style),
        ]);
    }
});

it('searches the main style and applies all local filters case-insensitively', function () {
    Http::fake(fn () => Http::response([
        'pagination' => ['pages' => 1],
        'results' => [
            releaseResult(1, ['Deep House', 'Dub Techno'], 1998, 'UK', ['Vinyl', '12"'], 20, 30),
            releaseResult(2, ['Deep House'], 1999, 'UK', ['Vinyl', '12"'], 10, 100),
            releaseResult(3, ['Deep House', 'Dub Techno', 'Ambient'], 1997, 'UK', ['Vinyl', '12"'], 5, 50),
            releaseResult(4, ['Deep House', 'Dub Techno'], 2005, 'UK', ['Vinyl', '12"'], 5, 50),
            releaseResult(5, ['Deep House', 'Dub Techno'], 1996, 'US', ['Vinyl', '12"'], 5, 50),
        ],
    ]));

    $results = app(StyleDigger::class)->dig('deep house', new DigOptions(
        genre: 'electronic',
        also: ['dub techno'],
        exclude: ['ambient'],
        yearFrom: 1994,
        yearTo: 2002,
        country: 'uk',
        format: '12"',
        maxHave: 25,
        minWant: 20,
        limit: 10,
    ));

    expect($results)->toHaveCount(1)
        ->and($results[0]->id)->toBe(1)
        ->and($results[0]->wantRatio())->toBe(1.5);

    Http::assertSent(fn (Request $request) => $request['style'] === 'Deep House, Dub Techno'
        && $request['genre'] === 'Electronic'
        && $request['country'] === 'uk'
        && $request['format'] === '12"'
        && $request['year'] === '1994-2002'
    );
});

it('requires the main style together with every additional style', function () {
    Http::fake(fn () => Http::response([
        'pagination' => ['pages' => 1],
        'results' => [
            releaseResult(11, ['Dub Techno'], 1998, 'UK', ['Vinyl'], 10, 20),
            releaseResult(12, ['Deep House', 'Dub Techno'], 1998, 'UK', ['Vinyl'], 10, 20),
            releaseResult(13, ['Deep House'], 1998, 'UK', ['Vinyl'], 10, 20),
        ],
    ]));

    $results = app(StyleDigger::class)->dig('deep house', new DigOptions(
        also: ['dub techno'],
    ));

    expect(array_column($results, 'id'))->toBe([12]);
});

it('sorts by want ratio by default and supports all requested sort modes', function (string $sort, array $expected) {
    $releases = [
        releaseData(1, 10, 20, 1999),
        releaseData(2, 100, 100, 2002),
        releaseData(3, 5, 15, 1995),
    ];

    $results = app(ReleaseFilter::class)->apply($releases, new DigOptions(sort: $sort));

    expect(array_column($results, 'id'))->toBe($expected);
})->with([
    ['want_ratio', [3, 1, 2]],
    ['have', [2, 1, 3]],
    ['want', [2, 1, 3]],
    ['year', [2, 1, 3]],
]);

it('forwards any style missing from the local taxonomy to Discogs', function (string $style) {
    Http::fake(fn () => Http::response([
        'pagination' => ['pages' => 1],
        'results' => [],
    ]));

    $this->artisan('crate:dig', ['style' => $style])
        ->expectsOutputToContain('Nessuna release corrisponde')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request['style'] === $style);
})->with(['Balearic', 'Newly Added Discogs Style']);

it('renders filtered releases from the artisan command', function () {
    Http::fake(fn () => Http::response([
        'pagination' => ['pages' => 1],
        'results' => [releaseResult(91, ['Deep House'], 1997, 'Italy', ['Vinyl', '12"'], 12, 36)],
    ]));

    $this->artisan('crate:dig', [
        'style' => 'DEEP HOUSE',
        '--years' => '1994-2002',
        '--format' => 'Vinyl',
        '--limit' => '5',
    ])
        ->expectsOutputToContain('Unknown Artist')
        ->expectsOutputToContain('1 release trovate.')
        ->assertSuccessful();
});

it('seeds the complete local Discogs taxonomy', function () {
    Style::query()->delete();
    Genre::query()->delete();

    $this->seed(DiscogsTaxonomySeeder::class);

    expect(Genre::count())->toBe(15)
        ->and(Style::count())->toBeGreaterThan(580)
        ->and(Style::where('normalized_name', 'deep house')->exists())->toBeTrue();
});

function releaseResult(
    int $id,
    array $styles,
    int $year,
    string $country,
    array $formats,
    int $have,
    int $want,
): array {
    return [
        'id' => $id,
        'title' => "Unknown Artist - Hidden Cut {$id}",
        'label' => ['Tiny Label'],
        'catno' => "TINY-{$id}",
        'year' => $year,
        'country' => $country,
        'format' => $formats,
        'genre' => ['Electronic'],
        'style' => $styles,
        'community' => ['have' => $have, 'want' => $want],
        'uri' => "/release/{$id}",
    ];
}

function releaseData(int $id, int $have, int $want, int $year): ReleaseData
{
    return new ReleaseData(
        id: $id,
        artist: 'Artist',
        title: 'Title',
        labels: [],
        catalogNumber: null,
        year: $year,
        country: null,
        formats: [],
        genres: [],
        styles: [],
        have: $have,
        want: $want,
        url: '',
    );
}
