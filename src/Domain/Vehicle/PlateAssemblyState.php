<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

final readonly class PlateAssemblyState
{
    public function __construct(
        public ?string $part1 = null,
        public ?float $part1At = null,
        public ?string $part2 = null,
        public ?float $part2At = null,
        public ?string $plate = null,
    ) {
    }
}
