<?php

declare(strict_types=1);

namespace App\Domain\Ingestion;

/**
 * The outcome of validating a record that could not be accepted.
 */
final readonly class Rejection
{
    public function __construct(
        public string $reason,
    ) {
    }
}
