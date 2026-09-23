<?php

use App\Livewire\Pages\Dig;
use App\Models\Genre;
use App\Models\Style;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the digging page', function () {
    Livewire::test(Dig::class)->assertSee('Trova il prossimo disco');
});

it('validates the required style', function () {
    Livewire::test(Dig::class)
        ->set('style', '')
        ->call('search')
        ->assertHasErrors(['style' => 'required']);
});

it('searches a Discogs style that is missing from the local taxonomy', function () {
    config()->set(['cache.default' => 'array', 'discogs.token' => 'test-token']);
    Http::preventStrayRequests();
    Http::fake(['https://api.discogs.com/database/search*' => Http::response([
        'pagination' => ['pages' => 1],
        'results' => [],
    ])]);

    Livewire::test(Dig::class)
        ->set('style', 'Balearic')
        ->call('search')
        ->assertSet('style', 'Balearic')
        ->assertSet('error', null)
        ->assertSet('results', []);

    Http::assertSent(fn ($request) => $request->data()['style'] === 'Balearic');
});

it('searches persists and renders escaped Discogs releases', function () {
    config()->set(['cache.default' => 'array', 'discogs.token' => 'test-token']);
    $genre = Genre::create(['name' => 'Electronic', 'normalized_name' => 'electronic']);
    Style::create(['genre_id' => $genre->id, 'name' => 'Deep House', 'normalized_name' => 'deep house']);
    Http::preventStrayRequests();
    Http::fake(['https://api.discogs.com/database/search*' => Http::response([
        'pagination' => ['pages' => 1],
        'results' => [[
            'id' => 501,
            'title' => '<script>alert(1)</script> - Track',
            'label' => ['Safe Label'],
            'catno' => 'SAFE-1',
            'year' => 2001,
            'country' => 'IT',
            'format' => ['Vinyl'],
            'genre' => ['Electronic'],
            'style' => ['Deep House'],
            'community' => ['have' => 10, 'want' => 30],
            'uri' => '/release/501',
        ]],
    ])]);

    Livewire::test(Dig::class)
        ->set('style', 'deep house')
        ->set('genre', 'electronic')
        ->call('search')
        ->assertSet('style', 'Deep House')
        ->assertSet('genre', 'Electronic')
        ->assertSee('Track')
        ->assertDontSeeHtml('<script>alert(1)</script>');

    $this->assertDatabaseHas('releases', ['discogs_id' => 501]);
    $this->assertDatabaseHas('searches', ['type' => 'dig', 'query' => 'Deep House']);
});

it('combines the main style and additional styles in any mode', function () {
    config()->set(['cache.default' => 'array', 'discogs.token' => 'test-token']);
    $genre = Genre::create(['name' => 'Electronic', 'normalized_name' => 'electronic']);
    Style::create(['genre_id' => $genre->id, 'name' => 'Trance', 'normalized_name' => 'trance']);
    Style::create(['genre_id' => $genre->id, 'name' => 'Progressive House', 'normalized_name' => 'progressive house']);
    Http::preventStrayRequests();
    Http::fake(function ($request) {
        $style = $request->data()['style'];
        $progressive = $style === 'Progressive House';

        return Http::response([
            'pagination' => ['pages' => 1],
            'results' => [
                [
                    'id' => $progressive ? 702 : 701,
                    'title' => $progressive ? 'Artist B - Progressive Cut' : 'Artist A - Trance Cut',
                    'label' => ['Test Label'],
                    'catno' => $progressive ? 'TEST-2' : 'TEST-1',
                    'year' => 1994,
                    'country' => 'UK',
                    'format' => ['Vinyl'],
                    'genre' => ['Electronic'],
                    'style' => [$style],
                    'community' => ['have' => 10, 'want' => 200],
                    'uri' => '/release/'.($progressive ? 702 : 701),
                ],
                [
                    'id' => 703,
                    'title' => 'Artist C - Combined Cut',
                    'label' => ['Test Label'],
                    'catno' => 'TEST-3',
                    'year' => 1994,
                    'country' => 'UK',
                    'format' => ['Vinyl'],
                    'genre' => ['Electronic'],
                    'style' => ['Trance', 'Progressive House'],
                    'community' => ['have' => 10, 'want' => 10],
                    'uri' => '/release/703',
                ],
            ],
        ]);
    });

    Livewire::test(Dig::class)
        ->set('style', 'trance')
        ->set('also', 'progressive house')
        ->set('styleMatch', 'any')
        ->call('search')
        ->assertSet('style', 'Trance')
        ->assertSet('also', 'Progressive House')
        ->assertCount('results', 3)
        ->assertSet('results.0.id', 703)
        ->assertSee('Trance Cut')
        ->assertSee('Progressive Cut')
        ->assertSee('Combined Cut');

    Http::assertSentCount(2);
});
