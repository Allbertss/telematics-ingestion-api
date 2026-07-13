<?php

declare(strict_types=1);

namespace App\Domain\Report;

/**
 * Computes distance travelled and fuel consumed from cumulative-counter deltas.
 */
final class DistanceFuelCalculator
{
    /**
     * @param list<list<CounterReading>> $segments
     */
    public function calculate(array $segments): DistanceFuelReport
    {
        $distanceMeters = 0;
        $fuelMilliliters = 0;

        foreach ($segments as $segment) {
            $ordered = $this->orderByTimestamp($segment);

            $distanceMeters += $this->sumPositiveDeltas($ordered, static fn (CounterReading $r): ?int => $r->odometerMeters);
            $fuelMilliliters += $this->sumPositiveDeltas($ordered, static fn (CounterReading $r): ?int => $r->fuelUsedMilliliters);
        }

        return new DistanceFuelReport(
            distanceKm: $distanceMeters / 1000,
            fuelLitres: $fuelMilliliters / 1000,
        );
    }

    /**
     * @param list<CounterReading>           $readings ordered readings
     * @param callable(CounterReading): ?int $select   picks the counter to read
     */
    private function sumPositiveDeltas(array $readings, callable $select): int
    {
        $total = 0;
        $previous = null;

        foreach ($readings as $reading) {
            $value = $select($reading);

            if (null === $value) {
                continue;
            }

            if (null !== $previous && $value > $previous) {
                $total += $value - $previous;
            }

            $previous = $value;
        }

        return $total;
    }

    /**
     * @param list<CounterReading> $segment
     *
     * @return list<CounterReading>
     */
    private function orderByTimestamp(array $segment): array
    {
        usort(
            $segment,
            static fn (CounterReading $a, CounterReading $b): int => $a->timestamp <=> $b->timestamp,
        );

        return $segment;
    }
}
