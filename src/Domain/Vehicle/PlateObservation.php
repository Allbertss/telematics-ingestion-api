<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

/**
 * A fully assembled plate observed for a device at a point in time.
 *
 * `observedAt` is the timestamp of the record that completed (or changed) the
 * plate — not now() and not the earlier half's timestamp.
 */
final readonly class PlateObservation
{
    public function __construct(
        public string $plate,
        public float $observedAt,
    ) {
    }
}
