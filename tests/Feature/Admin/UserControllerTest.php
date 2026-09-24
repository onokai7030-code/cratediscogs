<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the user directory for administrators', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['name' => 'Mario Rossi']);

    $response = $this->actingAs($admin)->get('/admin/users');

    $response->assertOk()->assertSeeText('Mario Rossi')->assertSeeText($user->email);
});

it('returns 403 when a regular user opens the user directory', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/users')->assertForbidden();
});
