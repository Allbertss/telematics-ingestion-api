<?php

declare(strict_types=1);

namespace App\Domain\Report;

/**
 * Distance/fuel calculation.
 *
 * Both counters are nullable — not every record carries every parameter.
 */
final readonly class CounterReading
{
    public function __construct(
        public float $timestamp,
        public ?int $odometerMeters = null,
        public ?int $fuelUsedMilliliters = null,
    ) {
    }
}
