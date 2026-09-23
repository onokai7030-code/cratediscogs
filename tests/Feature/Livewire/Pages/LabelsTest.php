<?php

use App\Livewire\Pages\Labels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the label radar and validates its input', function () {
    Livewire::test(Labels::class)
        ->assertSee('Etichette piccole')
        ->assertSee('Nome label')
        ->assertSee('Deve includere')
        ->set('style', '')
        ->call('search')
        ->assertHasErrors(['style' => 'required']);
});
