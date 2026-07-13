<?php

declare(strict_types=1);

namespace App\Domain\Report;

/**
 * Computes distance travelled and fuel consumed from cumulative-counter deltas.
 *
 * Each element of $segments is one independent counter chain (a single device
 * during a single contiguous plate window); deltas are never taken across a
 * segment boundary. Readings within a segment are ordered by timestamp
 * defensively, so the result does not depend on the caller's ordering.
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
     * Sums the positive deltas of one cumulative counter across ordered readings.
     *
     * A null sample is a gap and is skipped. Only forward movement is counted:
     * a non-positive delta is a counter reset (device swap, firmware reset,
     * 4-byte rollover) and re-baselines the chain instead of being subtracted.
     *
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
