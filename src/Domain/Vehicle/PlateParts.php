<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

/**
 * The registration-plate halves a single record carried (AVL 231 / 232), either
 * of which may be absent.
 */
final readonly class PlateParts
{
    public function __construct(
        public float $timestamp,
        public ?string $part1 = null,
        public ?string $part2 = null,
    ) {
    }
}
