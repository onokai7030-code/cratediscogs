<?php

namespace App\Data;

readonly class LabelExplorationData
{
    /**
     * @param  array<LabelData>  $sublabels
     * @param  array<ReleaseData>  $catalog
     * @param  array<string, int>  $topStyles
     */
    public function __construct(
        public LabelData $label,
        public ?LabelData $parent,
        public array $sublabels,
        public array $catalog,
        public array $topStyles,
    ) {}
}
