<?php

namespace App\Services\Digging;

use InvalidArgumentException;

class UnknownTaxonomyTerm extends InvalidArgumentException
{
    public function __construct(string $type, string $value, array $suggestions)
    {
        $message = ucfirst($type)." '{$value}' non trovato.";

        if ($suggestions !== []) {
            $message .= ' Forse cercavi: '.implode(', ', $suggestions).'?';
        }

        parent::__construct($message);
    }
}
