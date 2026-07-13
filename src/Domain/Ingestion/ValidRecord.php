<?php

declare(strict_types=1);

namespace App\Domain\Ingestion;

/**
 * A single record after validation and normalization: the known AVL parameters
 * extracted into typed fields, unknown parameters kept verbatim in `extra`.
 *
 * Every field except the timestamp is nullable — a record may legitimately
 * carry only context (e.g. ignition/movement) and no counters.
 */
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
