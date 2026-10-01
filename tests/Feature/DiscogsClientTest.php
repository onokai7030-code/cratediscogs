<?php

use App\Services\Discogs\DiscogsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    config()->set([
        'cache.default' => 'array',
        'discogs.token' => 'test-token',
        'discogs.user_agent' => 'Crate/0.1',
        'discogs.cache_days' => 7,
        'discogs.max_results' => 150,
    ]);

    Cache::flush();
});

it('sends the required authentication headers', function () {
    Http::fake([
        'api.discogs.com/releases/42' => Http::response(['id' => 42], 200, [
            'X-Discogs-Ratelimit-Remaining' => '57',
        ]),
    ]);

    $client = app(DiscogsClient::class);

    expect($client->release(42))->toBe(['id' => 42])
        ->and($client->rateLimitRemaining())->toBe(57);

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Discogs token=test-token')
        && $request->hasHeader('User-Agent', 'Crate/0.1')
    );
});

it('caches responses and allows bypassing the cache', function () {
    Http::fakeSequence()
        ->push(['id' => 1, 'title' => 'cached'])
        ->push(['id' => 1, 'title' => 'fresh']);

    $client = app(DiscogsClient::class);

    expect($client->release(1)['title'])->toBe('cached')
        ->and($client->release(1)['title'])->toBe('cached')
        ->and($client->release(1, fresh: true)['title'])->toBe('fresh');

    Http::assertSentCount(2);
});

it('paginates with 100 items per page and honors the configured maximum', function () {
    Http::fake(function (Request $request) {
        $page = (int) $request->data()['page'];
        $start = ($page - 1) * 100 + 1;

        return Http::response([
            'pagination' => ['page' => $page, 'pages' => 3],
            'results' => array_map(fn (int $id) => ['id' => $id], range($start, $start + 99)),
        ]);
    });

    $results = app(DiscogsClient::class)->search(['style' => 'Deep House']);

    expect($results)->toHaveCount(150)
        ->and($results[0]['id'])->toBe(1)
        ->and($results[149]['id'])->toBe(150);

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request) => $request['per_page'] === 100);
});

it('supports every phase one resource endpoint', function (string $method, array $arguments, string $url) {
    Http::fake(['*' => Http::response(['id' => 1, 'releases' => []])]);

    app(DiscogsClient::class)->{$method}(...$arguments);

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), $url));
})->with([
    ['release', [1], 'https://api.discogs.com/releases/1'],
    ['master', [1], 'https://api.discogs.com/masters/1'],
    ['label', [1], 'https://api.discogs.com/labels/1'],
    ['labelReleases', [1], 'https://api.discogs.com/labels/1/releases'],
    ['artist', [1], 'https://api.discogs.com/artists/1'],
    ['artistReleases', [1], 'https://api.discogs.com/artists/1/releases'],
]);

it('retries 429 responses with backoff instead of failing', function () {
    Sleep::fake();
    Http::fakeSequence()
        ->push(['message' => 'rate limit exceeded'], 429, ['Retry-After' => '2'])
        ->push(['id' => 99], 200, ['X-Discogs-Ratelimit-Remaining' => '40']);

    $release = app(DiscogsClient::class)->release(99);

    expect($release['id'])->toBe(99);
    Http::assertSentCount(2);
    Sleep::assertSlept(fn ($duration) => $duration->totalMilliseconds === 2000.0);
});

it('stops retrying after repeated rate limit responses', function () {
    Sleep::fake();
    Http::fake(['*' => Http::response(['message' => 'rate limit exceeded'], 429)]);

    expect(fn () => app(DiscogsClient::class)->release(99))
        ->toThrow(RequestException::class);
    Http::assertSentCount(5);
    Sleep::assertSleptTimes(4);
});

it('slows subsequent requests when the remaining quota is low', function () {
    Sleep::fake();
    Http::fakeSequence()
        ->push(['pagination' => ['pages' => 2], 'results' => [['id' => 1]]], 200, [
            'X-Discogs-Ratelimit-Remaining' => '4',
        ])
        ->push(['pagination' => ['pages' => 2], 'results' => [['id' => 2]]], 200, [
            'X-Discogs-Ratelimit-Remaining' => '30',
        ]);

    expect(app(DiscogsClient::class)->search([]))->toHaveCount(2);
    Sleep::assertSlept(fn ($duration) => $duration->totalSeconds === 1.0);
});
