<?php

declare(strict_types=1);

namespace App\Ingestion;

final readonly class RejectedRecord
{
    public function __construct(
        public int $index,
        public string $reason,
    ) {
    }
}
