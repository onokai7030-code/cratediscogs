<?php

use Illuminate\Support\Facades\Http;

it('reports the authenticated identity and remaining rate limit', function () {
    config()->set([
        'discogs.token' => 'test-token',
        'discogs.user_agent' => 'Crate/0.1',
    ]);

    Http::fake([
        'api.discogs.com/oauth/identity' => Http::response([
            'username' => 'crate_digger',
        ], 200, [
            'X-Discogs-Ratelimit-Remaining' => '58',
        ]),
    ]);

    $this->artisan('crate:ping')
        ->expectsOutput('Discogs raggiungibile.')
        ->expectsOutput('Utente: crate_digger')
        ->expectsOutput('Rate limit rimanente: 58')
        ->assertSuccessful();
});

it('explains how to configure a missing token', function () {
    config()->set('discogs.token', null);

    $this->artisan('crate:ping')
        ->expectsOutputToContain('DISCOGS_TOKEN non configurato')
        ->assertFailed();
});
