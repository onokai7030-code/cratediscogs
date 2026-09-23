<?php

use App\Models\Genre;
use App\Models\Style;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set([
        'cache.default' => 'array',
        'discogs.token' => 'test-token',
        'discogs.max_results' => 100,
    ]);

    $genre = Genre::create(['name' => 'Electronic', 'normalized_name' => 'electronic']);
    Style::create([
        'genre_id' => $genre->id,
        'name' => 'Deep House',
        'normalized_name' => 'deep house',
    ]);
});

it('persists output exports CSV and hides releases shown by an earlier dig', function () {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.discogs.com/database/search*' => Http::response([
            'pagination' => ['pages' => 1],
            'results' => [
                commandReleaseResult(),
                commandReleaseResult(id: 102, title: 'Artist - New Track', have: 20, want: 20),
            ],
        ]),
    ]);
    $exportPath = storage_path('framework/testing/dig.csv');

    $this->artisan('crate:dig', [
        'style' => 'Deep House',
        '--limit' => 1,
        '--export' => $exportPath,
    ])->expectsOutputToContain('1 release trovate.')
        ->expectsOutputToContain('CSV esportato in:')
        ->assertSuccessful();

    $this->artisan('crate:dig', [
        'style' => 'Deep House',
        '--limit' => 1,
        '--hide-seen' => true,
    ])->expectsOutputToContain('New Track')
        ->expectsOutputToContain('1 release trovate.')
        ->assertSuccessful();

    $this->assertDatabaseCount('releases', 2);
    $this->assertDatabaseCount('searches', 2);
    $this->assertDatabaseCount('seen_releases', 2);
    expect(file_get_contents($exportPath))->toContain('Artist,Track,"Focused Records"');

    unlink($exportPath);
});

function commandReleaseResult(
    int $id = 101,
    string $title = 'Artist - Track',
    int $have = 10,
    int $want = 30,
): array {
    return [
        'id' => $id,
        'title' => $title,
        'label' => ['Focused Records'],
        'catno' => 'FOCUS-1',
        'year' => 2000,
        'country' => 'IT',
        'format' => ['Vinyl'],
        'genre' => ['Electronic'],
        'style' => ['Deep House'],
        'community' => ['have' => $have, 'want' => $want],
        'uri' => "/release/{$id}",
    ];
}
