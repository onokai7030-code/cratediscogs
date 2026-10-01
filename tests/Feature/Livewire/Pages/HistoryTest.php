<?php

use App\Livewire\Pages\History;
use App\Models\Search;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders saved searches and filters them by type', function () {
    $user = User::factory()->create();
    Search::factory()->create(['user_id' => $user->id, 'type' => 'dig', 'query' => 'Deep House']);
    Search::factory()->create(['user_id' => $user->id, 'type' => 'labels', 'query' => 'Techno']);

    Livewire::actingAs($user)
        ->test(History::class)
        ->assertSee('Deep House')
        ->assertSee('Techno')
        ->set('type', 'labels')
        ->assertDontSee('Deep House')
        ->assertSee('Techno');
});

it('only renders searches owned by the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Search::factory()->create(['user_id' => $user->id, 'query' => 'My search']);
    Search::factory()->create(['user_id' => $otherUser->id, 'query' => 'Private search']);

    Livewire::actingAs($user)
        ->test(History::class)
        ->assertSee('My search')
        ->assertDontSee('Private search');
});
