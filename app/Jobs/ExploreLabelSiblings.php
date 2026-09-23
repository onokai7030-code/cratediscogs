<?php

namespace App\Jobs;

use App\Data\LabelData;
use App\Models\LabelExploration;
use App\Services\Digging\LabelExplorer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExploreLabelSiblings implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 3;

    public function __construct(
        public readonly int $explorationId,
        public readonly bool $fresh = false,
    ) {}

    public function handle(LabelExplorer $explorer): void
    {
        $exploration = LabelExploration::findOrFail($this->explorationId);
        $exploration->update([
            'status' => 'running',
            'progress' => 1,
            'message' => 'Avvio esplorazione delle label sorelle',
            'failed_reason' => null,
        ]);

        $siblings = $explorer->findSiblings(
            new LabelData($exploration->discogs_label_id, $exploration->label_name),
            function (int $completed, int $total, string $message) use ($exploration): void {
                $exploration->update([
                    'progress' => min(99, max(1, (int) floor($completed / max($total, 1) * 100))),
                    'message' => $message,
                ]);
            },
            $this->fresh,
        );

        $exploration->update([
            'status' => 'completed',
            'progress' => 100,
            'message' => 'Esplorazione completata',
            'results' => $siblings,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        LabelExploration::whereKey($this->explorationId)->update([
            'status' => 'failed',
            'message' => 'Esplorazione fallita',
            'failed_reason' => $exception?->getMessage(),
        ]);
    }
}
