<?php

declare(strict_types=1);

namespace App\Ingestion;

final readonly class IngestionSummary
{
    /**
     * @param list<RejectedRecord> $rejected
     */
    public function __construct(
        public int $received,
        public int $stored,
        public int $duplicates,
        public array $rejected,
    ) {
    }
}
