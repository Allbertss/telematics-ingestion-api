<?php

declare(strict_types=1);

namespace App\Domain\Ingestion;

final readonly class ValidRecord
{
    /**
     * @param array<array-key, mixed> $extra unknown AVL parameters, stored as-is
     */
    public function __construct(
        public float $timestamp,
        public ?float $latitude,
        public ?float $longitude,
        public ?int $altitudeMeters,
        public ?int $speedKmh,
        public ?bool $ignition,
        public ?bool $movement,
        public ?int $gsmSignal,
        public ?int $odometerMeters,
        public ?int $fuelUsedMilliliters,
        public ?string $platePart1,
        public ?string $platePart2,
        public array $extra,
    ) {
    }
}
