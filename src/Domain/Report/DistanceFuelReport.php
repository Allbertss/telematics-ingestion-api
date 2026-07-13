<?php

declare(strict_types=1);

namespace App\Domain\Report;

/**
 * The computed result of a distance/fuel calculation.
 */
final readonly class DistanceFuelReport
{
    public function __construct(
        public float $distanceKm,
        public float $fuelLitres,
    ) {
    }
}
