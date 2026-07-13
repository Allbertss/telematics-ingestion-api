<?php

declare(strict_types=1);

namespace App\Domain\Report;

final readonly class CounterReading
{
    public function __construct(
        public float $timestamp,
        public ?int $odometerMeters = null,
        public ?int $fuelUsedMilliliters = null,
    ) {
    }
}
