<?php

declare(strict_types=1);

namespace App\Domain\Report;

final readonly class DistanceFuelReport
{
    public function __construct(
        public float $distanceKm,
        public float $fuelLitres,
    ) {
    }
}
