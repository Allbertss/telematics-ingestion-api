<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

final readonly class PlateAssemblyResult
{
    /**
     * @param list<PlateObservation> $observations
     */
    public function __construct(
        public PlateAssemblyState $state,
        public array $observations,
    ) {
    }
}
