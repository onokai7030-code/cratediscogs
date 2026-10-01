<?php

use App\Livewire\Pages\LabelShow;
use App\Models\LabelExploration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the label explorer and validates its input', function () {
    Livewire::test(LabelShow::class)
        ->assertSee('Dentro una label')
        ->set('label', '')
        ->call('explore')
        ->assertHasErrors(['label' => 'required']);
});

it('does not render another user exploration', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $exploration = LabelExploration::create([
        'user_id' => $otherUser->id,
        'discogs_label_id' => 42,
        'label_name' => 'Private Label',
        'status' => 'running',
        'progress' => 50,
        'message' => 'Private progress message',
    ]);

    Livewire::actingAs($user)
        ->test(LabelShow::class)
        ->set('explorationId', $exploration->id)
        ->assertDontSee('Private progress message');
});
