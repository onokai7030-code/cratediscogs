<?php

use App\Livewire\Pages\History;
use App\Models\Search;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders saved searches and filters them by type', function () {
    Search::factory()->create(['type' => 'dig', 'query' => 'Deep House']);
    Search::factory()->create(['type' => 'labels', 'query' => 'Techno']);

    Livewire::test(History::class)
        ->assertSee('Deep House')
        ->assertSee('Techno')
        ->set('type', 'labels')
        ->assertDontSee('Deep House')
        ->assertSee('Techno');
});
