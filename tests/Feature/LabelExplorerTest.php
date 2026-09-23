<?php

use App\Data\LabelExploreOptions;
use App\Jobs\ExploreLabelSiblings;
use App\Models\LabelExploration;
use App\Services\Digging\LabelExplorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set([
        'cache.default' => 'array',
        'discogs.token' => 'test-token',
        'discogs.max_results' => 100,
    ]);
    Cache::flush();
});

it('resolves a label by name, id or Discogs URL', function (string $input) {
    fakeBasicLabelResponses();

    $label = app(LabelExplorer::class)->resolve($input);

    expect($label->id)->toBe(10)
        ->and($label->name)->toBe('Target Label');
})->with([
    ['Target Label'],
    ['10'],
    ['https://www.discogs.com/label/10-Target-Label'],
]);

it('returns relationships, filtered catalog and top styles', function () {
    fakeBasicLabelResponses();

    $data = app(LabelExplorer::class)->explore('10', new LabelExploreOptions(
        yearFrom: 1995,
        yearTo: 2000,
        format: 'Vinyl',
        styles: ['Deep House'],
    ));

    expect($data->parent?->name)->toBe('Parent Label')
        ->and(array_column($data->sublabels, 'name'))->toBe(['Sub One', 'Sub Two'])
        ->and($data->catalog)->toHaveCount(1)
        ->and($data->catalog[0]->id)->toBe(101)
        ->and($data->topStyles)->toBe([
            'Deep House' => 2,
            'Dub Techno' => 1,
            'Techno' => 1,
        ]);
});

it('queues depth two and exposes terminal progress', function () {
    Queue::fake();
    fakeBasicLabelResponses();

    $this->artisan('crate:label', [
        'label' => '10',
        '--depth' => '2',
        '--format' => 'Vinyl',
    ])
        ->expectsOutputToContain('Target Label [Discogs #10]')
        ->expectsOutputToContain('Job label sorelle accodato: #1')
        ->assertSuccessful();

    Queue::assertPushed(ExploreLabelSiblings::class);

    $exploration = LabelExploration::firstOrFail();
    expect($exploration->status)->toBe('pending')
        ->and($exploration->progress)->toBe(0);

    $this->artisan('crate:label:status', ['id' => $exploration->id])
        ->expectsOutputToContain('Avanzamento: 0%')
        ->assertSuccessful();
});

it('finds sibling labels by distinct shared artists in the queued job', function () {
    Http::fake(function (Request $request) {
        $url = $request->url();

        if (str_contains($url, '/database/search')) {
            return Http::response([
                'pagination' => ['pages' => 1],
                'results' => [
                    ['id' => 201],
                    ['id' => 202],
                ],
            ]);
        }

        if (str_contains($url, '/releases/201')) {
            return Http::response(['artists' => [
                ['id' => 1, 'name' => 'Artist One'],
                ['id' => 2, 'name' => 'Artist Two'],
            ]]);
        }

        if (str_contains($url, '/releases/202')) {
            return Http::response(['artists' => [
                ['id' => 2, 'name' => 'Artist Two'],
            ]]);
        }

        $labels = str_contains($url, '/artists/1/')
            ? ['Target Label', 'Sister A', 'Sister B']
            : ['Target Label', 'Sister A'];

        return Http::response([
            'pagination' => ['pages' => 1],
            'releases' => array_map(fn (string $label) => ['label' => $label], $labels),
        ]);
    });

    $exploration = LabelExploration::create([
        'discogs_label_id' => 10,
        'label_name' => 'Target Label',
        'status' => 'pending',
    ]);

    (new ExploreLabelSiblings($exploration->id))->handle(app(LabelExplorer::class));

    $exploration->refresh();

    expect($exploration->status)->toBe('completed')
        ->and($exploration->progress)->toBe(100)
        ->and($exploration->results)->toBe([
            ['name' => 'Sister A', 'shared_artists' => 2],
            ['name' => 'Sister B', 'shared_artists' => 1],
        ]);
});

function fakeBasicLabelResponses(): void
{
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), '/labels/10')) {
            return Http::response([
                'id' => 10,
                'name' => 'Target Label',
                'parent_label' => ['id' => 5, 'name' => 'Parent Label'],
                'sublabels' => [
                    ['id' => 11, 'name' => 'Sub One'],
                    ['id' => 12, 'name' => 'Sub Two'],
                ],
            ]);
        }

        if (($request['type'] ?? null) === 'label') {
            return Http::response([
                'pagination' => ['pages' => 1],
                'results' => [['id' => 10, 'title' => 'Target Label']],
            ]);
        }

        return Http::response([
            'pagination' => ['pages' => 1],
            'results' => [
                explorerRelease(101, 1998, ['Vinyl', '12"'], ['Deep House', 'Dub Techno']),
                explorerRelease(102, 2005, ['CD'], ['Deep House', 'Techno']),
            ],
        ]);
    });
}

function explorerRelease(int $id, int $year, array $formats, array $styles): array
{
    return [
        'id' => $id,
        'title' => "Artist {$id} - Release {$id}",
        'label' => ['Target Label'],
        'catno' => "TARGET-{$id}",
        'year' => $year,
        'format' => $formats,
        'genre' => ['Electronic'],
        'style' => $styles,
        'community' => ['have' => 10, 'want' => 20],
        'uri' => "/release/{$id}",
    ];
}
