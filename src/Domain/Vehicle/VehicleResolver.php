<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

/**
 * Resolves which vehicle (plate) a record belonged to.
 */
final class VehicleResolver
{
    /**
     * @param list<PlateObservation> $observations
     */
    public function resolvePlateAt(array $observations, float $timestamp): ?string
    {
        $plate = null;
        $resolvedAt = null;

        foreach ($observations as $observation) {
            if ($observation->observedAt > $timestamp) {
                continue;
            }

            if (null === $resolvedAt || $observation->observedAt >= $resolvedAt) {
                $plate = $observation->plate;
                $resolvedAt = $observation->observedAt;
            }
        }

        return $plate;
    }
}
