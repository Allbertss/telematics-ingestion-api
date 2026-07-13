<?php

declare(strict_types=1);

namespace App\Domain\Ingestion;

final readonly class Rejection
{
    public function __construct(
        public string $reason,
    ) {
    }
}
