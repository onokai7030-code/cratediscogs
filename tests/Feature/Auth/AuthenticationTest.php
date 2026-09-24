<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('redirects guests from the workspace to login', function () {
    $this->get('/dig')->assertRedirectToRoute('login');
});

it('authenticates registered users and regenerates the session', function () {
    $user = User::factory()->create(['password' => Hash::make('secret-password')]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'secret-password',
    ]);

    $response->assertRedirectToRoute('dig');
    $this->assertAuthenticatedAs($user);
});

it('rejects invalid credentials', function () {
    $user = User::factory()->create();

    $response = $this->from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertRedirect('/login')->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('logs authenticated users out', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirectToRoute('login');
    $this->assertGuest();
});
