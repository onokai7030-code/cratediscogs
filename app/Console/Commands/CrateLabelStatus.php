<?php

namespace App\Console\Commands;

use App\Models\LabelExploration;
use Illuminate\Console\Command;

class CrateLabelStatus extends Command
{
    protected $signature = 'crate:label:status {id : ID del Job di esplorazione}';

    protected $description = 'Mostra avanzamento e risultati di una ricerca di label sorelle';

    public function handle(): int
    {
        $exploration = LabelExploration::find($this->argument('id'));

        if ($exploration === null) {
            $this->error('Esplorazione non trovata.');

            return self::FAILURE;
        }

        $this->line("Label: {$exploration->label_name}");
        $this->line("Stato: {$exploration->status}");
        $this->line("Avanzamento: {$exploration->progress}%");
        $this->line("Dettaglio: {$exploration->message}");

        if ($exploration->status === 'failed') {
            $this->error((string) $exploration->failed_reason);

            return self::FAILURE;
        }

        if ($exploration->status === 'completed') {
            $this->table(
                ['Label sorella', 'Artisti condivisi'],
                collect($exploration->results)->map(fn (array $label) => [
                    $label['name'],
                    $label['shared_artists'],
                ])->all(),
            );
        }

        return self::SUCCESS;
    }
}
