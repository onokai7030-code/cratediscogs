<?php

namespace App\Console\Commands;

use App\Services\Discogs\DiscogsClient;
use App\Services\Discogs\DiscogsException;
use Illuminate\Console\Command;

class CratePing extends Command
{
    protected $signature = 'crate:ping';

    protected $description = 'Verifica il token Discogs e mostra il rate limit disponibile';

    public function handle(DiscogsClient $client): int
    {
        try {
            $identity = $client->identity();
        } catch (DiscogsException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $this->error('Connessione a Discogs fallita: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Discogs raggiungibile.');
        $this->line('Utente: '.($identity['username'] ?? 'sconosciuto'));
        $this->line('Rate limit rimanente: '.($client->rateLimitRemaining() ?? 'non disponibile'));

        return self::SUCCESS;
    }
}
