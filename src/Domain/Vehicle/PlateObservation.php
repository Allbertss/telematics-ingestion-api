<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

final readonly class PlateObservation
{
    public function __construct(
        public string $plate,
        public float $observedAt,
    ) {
    }
}
