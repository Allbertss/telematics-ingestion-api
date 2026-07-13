<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

/**
 * Assembles a vehicle registration plate from its two halves (AVL 231 + 232),
 * which may arrive in different records and across different batches.
 *
 * Readings are ordered by timestamp defensively, so the result does not depend
 * on the caller's ordering.
 */
final class PlateAssembler
{
    /**
     * @param list<PlateParts> $readings one device's plate-part readings
     *
     * @return list<PlateObservation>
     */
    public function assemble(array $readings): array
    {
        $observations = [];
        $part1 = null;
        $part2 = null;
        $lastEmitted = null;

        foreach ($this->orderByTimestamp($readings) as $reading) {
            $incomingPart1 = $this->normalize($reading->part1);
            $incomingPart2 = $this->normalize($reading->part2);

            if (null === $incomingPart1 && null === $incomingPart2) {
                continue;
            }

            if (null !== $incomingPart1) {
                $part1 = $incomingPart1;
            }

            if (null !== $incomingPart2) {
                $part2 = $incomingPart2;
            }

            if (null === $part1 || null === $part2) {
                continue;
            }

            $plate = $part1.$part2;

            if ($plate !== $lastEmitted) {
                $observations[] = new PlateObservation($plate, $reading->timestamp);
                $lastEmitted = $plate;
            }
        }

        return $observations;
    }

    private function normalize(?string $part): ?string
    {
        if (null === $part) {
            return null;
        }

        $normalized = strtoupper(trim($part));

        return '' === $normalized ? null : $normalized;
    }

    /**
     * @param list<PlateParts> $readings
     *
     * @return list<PlateParts>
     */
    private function orderByTimestamp(array $readings): array
    {
        usort(
            $readings,
            static fn (PlateParts $a, PlateParts $b): int => $a->timestamp <=> $b->timestamp,
        );

        return $readings;
    }
}
