<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers the first account as administrator', function () {
    $response = $this->post('/register', [
        'name' => 'Crate Admin',
        'email' => 'ADMIN@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]);

    $response->assertRedirectToRoute('dig');
    $user = User::query()->sole();
    expect($user->email)->toBe('admin@example.com')
        ->and($user->is_admin)->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

it('registers later accounts without administrator privileges', function () {
    User::factory()->admin()->create();

    $response = $this->post('/register', [
        'name' => 'Regular User',
        'email' => 'user@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        'is_admin' => true,
    ]);

    $response->assertRedirectToRoute('dig');
    expect(User::query()->where('email', 'user@example.com')->sole()->is_admin)->toBeFalse();
});

it('validates unique email and confirmed password', function () {
    User::factory()->create(['email' => 'used@example.com']);

    $response = $this->from('/register')->post('/register', [
        'name' => 'New User',
        'email' => 'used@example.com',
        'password' => 'secret-password',
        'password_confirmation' => 'different-password',
    ]);

    $response->assertRedirect('/register')->assertSessionHasErrors(['email', 'password']);
});
