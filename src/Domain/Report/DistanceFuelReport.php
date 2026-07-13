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

    public function fuelPer100Km(): ?float
    {
        if ($this->distanceKm <= 0.0) {
            return null;
        }

        return $this->fuelLitres / $this->distanceKm * 100;
    }
}
