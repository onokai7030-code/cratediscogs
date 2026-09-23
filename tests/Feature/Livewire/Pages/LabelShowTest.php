<?php

use App\Livewire\Pages\LabelShow;
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
