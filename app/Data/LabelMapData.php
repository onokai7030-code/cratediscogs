<?php

namespace App\Data;

readonly class LabelMapData
{
    public function __construct(
        public string $name,
        public int $styleReleaseCount,
        public ?int $activeFrom,
        public ?int $activeTo,
        public float $averageWantRatio,
        public float $specialization,
        public int $catalogSize,
    ) {}
}
