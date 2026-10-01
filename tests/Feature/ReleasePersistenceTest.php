<?php

use App\Data\LabelMapData;
use App\Data\ReleaseData;
use App\Models\Genre;
use App\Models\Style;
use App\Models\User;
use App\Services\Export\CsvExporter;
use App\Services\Persistence\ReleasePersister;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists releases and their labels styles and search position', function () {
    $genre = Genre::create(['name' => 'Electronic', 'normalized_name' => 'electronic']);
    Style::create(['genre_id' => $genre->id, 'name' => 'Deep House', 'normalized_name' => 'deep house']);
    $release = persistenceReleaseData();

    $search = app(ReleasePersister::class)->persistSearch('dig', 'Deep House', ['limit' => 10], [$release]);

    expect($search->parameters)->toBe(['limit' => 10])
        ->and($search->releases()->firstOrFail()->pivot->position)->toBe(1);
    $this->assertDatabaseHas('releases', ['discogs_id' => 101, 'have' => 10, 'want' => 30]);
    $this->assertDatabaseHas('labels', ['normalized_name' => 'focused records']);
    $this->assertDatabaseCount('label_release', 1);
    $this->assertDatabaseCount('release_style', 1);
});

it('updates an existing Discogs release without duplicating its domain records', function () {
    $genre = Genre::create(['name' => 'Electronic', 'normalized_name' => 'electronic']);
    Style::create(['genre_id' => $genre->id, 'name' => 'Deep House', 'normalized_name' => 'deep house']);
    $persister = app(ReleasePersister::class);
    $persister->persistSearch('dig', 'Deep House', [], [persistenceReleaseData()]);
    $updated = persistenceReleaseData(title: 'Updated title', have: 20);

    $persister->persistSearch('dig', 'Deep House', [], [$updated]);

    $this->assertDatabaseCount('releases', 1);
    $this->assertDatabaseCount('labels', 1);
    $this->assertDatabaseHas('releases', ['discogs_id' => 101, 'title' => 'Updated title', 'have' => 20]);
    $this->assertDatabaseCount('searches', 2);
});

it('persists duplicate Discogs results only once per search', function () {
    $release = persistenceReleaseData();

    $search = app(ReleasePersister::class)->persistSearch('dig', 'Balearic', [], [$release, $release]);

    expect($search->result_count)->toBe(1)
        ->and($search->releases()->count())->toBe(1);
    $this->assertDatabaseCount('release_search', 1);
});

it('marks displayed releases and removes them from later hidden results', function () {
    $genre = Genre::create(['name' => 'Electronic', 'normalized_name' => 'electronic']);
    Style::create(['genre_id' => $genre->id, 'name' => 'Deep House', 'normalized_name' => 'deep house']);
    $release = persistenceReleaseData();
    $persister = app(ReleasePersister::class);
    $persister->persistSearch('dig', 'Deep House', [], [$release]);

    $persister->markSeen([$release]);

    expect($persister->withoutSeen([$release]))->toBe([]);
    $this->assertDatabaseCount('seen_releases', 1);
});

it('keeps seen releases isolated between users', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $release = persistenceReleaseData();
    $persister = app(ReleasePersister::class);
    $persister->persistSearch('dig', 'Deep House', [], [$release], $firstUser);

    $persister->markSeen([$release], $firstUser);

    expect($persister->withoutSeen([$release], $firstUser))->toBe([])
        ->and($persister->withoutSeen([$release], $secondUser))->toBe([$release]);
    $this->assertDatabaseHas('seen_releases', ['user_id' => $firstUser->id]);
});

it('exports release and label CSV files with stable headers and safe spreadsheet cells', function () {
    $releasePath = storage_path('framework/testing/releases.csv');
    $labelPath = storage_path('framework/testing/labels.csv');
    $release = persistenceReleaseData(artist: '=unsafe');

    app(CsvExporter::class)->releases($releasePath, [$release]);
    app(CsvExporter::class)->labels($labelPath, [new LabelMapData('Focused Records', 2, 1999, 2000, 3.0, 0.8, 10)]);

    expect(file_get_contents($releasePath))->toContain('artista,titolo,label')
        ->toContain("'=unsafe")
        ->and(file_get_contents($labelPath))->toContain('label,release_stile,attiva_da');

    unlink($releasePath);
    unlink($labelPath);
});

function persistenceReleaseData(string $artist = 'Artist', string $title = 'Track', int $have = 10): ReleaseData
{
    return new ReleaseData(
        id: 101,
        artist: $artist,
        title: $title,
        labels: ['Focused Records'],
        catalogNumber: 'FOCUS-1',
        year: 2000,
        country: 'IT',
        formats: ['Vinyl'],
        genres: ['Electronic'],
        styles: ['Deep House'],
        have: $have,
        want: 30,
        url: 'https://www.discogs.com/release/101',
    );
}
