<?php

use App\Data\DigOptions;
use App\Models\Genre;
use App\Models\Style;
use App\Services\Digging\LabelMapper;
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
    Style::create([
        'genre_id' => $genre->id,
        'name' => 'Deep House',
        'normalized_name' => 'deep house',
    ]);
});

it('aggregates and ranks focused labels by sampled specialization', function () {
    fakeLabelMappingResponses();

    $labels = app(LabelMapper::class)->map('deep house', new DigOptions(limit: 3));

    expect(array_column($labels, 'name'))->toBe(['Focused Records', 'Mixed Records', 'Major Records'])
        ->and($labels[0]->styleReleaseCount)->toBe(2)
        ->and($labels[0]->activeFrom)->toBe(1996)
        ->and($labels[0]->activeTo)->toBe(1998)
        ->and($labels[0]->averageWantRatio)->toBe(3.5)
        ->and($labels[0]->specialization)->toBe(1.0)
        ->and($labels[0]->catalogSize)->toBe(4)
        ->and($labels[2]->specialization)->toBe(1 / 3)
        ->and($labels[2]->catalogSize)->toBe(1000);
});

it('excludes labels whose complete catalog is too large', function () {
    fakeLabelMappingResponses();

    $labels = app(LabelMapper::class)->map(
        'Deep House',
        new DigOptions(limit: 10),
        maxReleases: 100,
    );

    expect(array_column($labels, 'name'))
        ->toContain('Focused Records', 'Mixed Records')
        ->not->toContain('Major Records');
});

it('filters label names case-insensitively', function () {
    fakeLabelMappingResponses();

    $labels = app(LabelMapper::class)->map(
        'deep house',
        new DigOptions(limit: 10),
        labelName: 'focused',
    );

    expect(array_column($labels, 'name'))->toBe(['Focused Records']);
});

it('renders the label map from artisan', function () {
    fakeLabelMappingResponses();

    $this->artisan('crate:labels', [
        'style' => 'DEEP HOUSE',
        '--max-releases' => '100',
        '--limit' => '2',
    ])
        ->expectsOutputToContain('Focused Records')
        ->expectsOutputToContain('2 label trovate.')
        ->assertSuccessful();
});

function fakeLabelMappingResponses(): void
{
    Http::fake(function (Request $request) {
        if (($request['style'] ?? null) === 'Deep House') {
            return Http::response([
                'pagination' => ['pages' => 1, 'items' => 4],
                'results' => [
                    labelRelease(1, ['Focused Records'], ['Deep House'], 1996, 10, 40),
                    labelRelease(2, ['Focused Records'], ['Deep House'], 1998, 20, 60),
                    labelRelease(3, ['Mixed Records'], ['Deep House'], 2000, 10, 20),
                    labelRelease(4, ['Major Records'], ['Deep House'], 2001, 100, 100),
                ],
            ]);
        }

        return match ($request['label']) {
            'Focused Records' => labelSample(4, [['Deep House'], ['Deep House'], ['Deep House'], ['Deep House']]),
            'Mixed Records' => labelSample(20, [['Deep House'], ['House'], ['Deep House'], ['Techno']]),
            'Major Records' => labelSample(1000, [['Pop'], ['Deep House'], ['Rock']]),
            default => Http::response(['pagination' => ['items' => 0], 'results' => []]),
        };
    });
}

function labelRelease(
    int $id,
    array $labels,
    array $styles,
    int $year,
    int $have,
    int $want,
): array {
    return [
        'id' => $id,
        'title' => "Artist {$id} - Track {$id}",
        'label' => $labels,
        'year' => $year,
        'format' => ['Vinyl'],
        'genre' => ['Electronic'],
        'style' => $styles,
        'community' => ['have' => $have, 'want' => $want],
        'uri' => "/release/{$id}",
    ];
}

function labelSample(int $total, array $styles): mixed
{
    return Http::response([
        'pagination' => ['pages' => 1, 'items' => $total],
        'results' => array_map(
            fn (array $releaseStyles, int $index) => labelRelease(
                100 + $index,
                ['Sample Label'],
                $releaseStyles,
                2000,
                1,
                1,
            ),
            $styles,
            array_keys($styles),
        ),
    ]);
}
