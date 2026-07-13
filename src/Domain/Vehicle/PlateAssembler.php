<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

final class PlateAssembler
{
    /**
     * @param list<PlateParts> $readings one device's plate-part readings
     *
     * @return list<PlateObservation>
     */
    public function assemble(array $readings): array
    {
        return $this->accumulate($readings, new PlateAssemblyState())->observations;
    }

    /**
     * @param list<PlateParts> $readings
     */
    public function accumulate(array $readings, PlateAssemblyState $initial): PlateAssemblyResult
    {
        $part1 = $initial->part1;
        $part1At = $initial->part1At;
        $part2 = $initial->part2;
        $part2At = $initial->part2At;
        $lastPlate = $initial->plate;
        $observations = [];

        foreach ($this->orderByTimestamp($readings) as $reading) {
            $incomingPart1 = $this->normalize($reading->part1);
            $incomingPart2 = $this->normalize($reading->part2);

            if (null === $incomingPart1 && null === $incomingPart2) {
                continue;
            }

            if (null !== $incomingPart1 && (null === $part1At || $reading->timestamp >= $part1At)) {
                $part1 = $incomingPart1;
                $part1At = $reading->timestamp;
            }

            if (null !== $incomingPart2 && (null === $part2At || $reading->timestamp >= $part2At)) {
                $part2 = $incomingPart2;
                $part2At = $reading->timestamp;
            }

            if (null === $part1 || null === $part2) {
                continue;
            }

            $plate = $part1.$part2;

            if ($plate !== $lastPlate) {
                $observations[] = new PlateObservation($plate, $reading->timestamp);
                $lastPlate = $plate;
            }
        }

        return new PlateAssemblyResult(
            new PlateAssemblyState($part1, $part1At, $part2, $part2At, $lastPlate),
            $observations,
        );
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
