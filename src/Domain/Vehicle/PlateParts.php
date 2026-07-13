<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

final readonly class PlateParts
{
    public function __construct(
        public float $timestamp,
        public ?string $part1 = null,
        public ?string $part2 = null,
    ) {
    }
}
