<?php

declare(strict_types=1);

namespace App\Tests\Domain\Report;

use App\Domain\Report\CounterReading;
use App\Domain\Report\DistanceFuelCalculator;
use PHPUnit\Framework\TestCase;

final class DistanceFuelCalculatorTest extends TestCase
{
    private DistanceFuelCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new DistanceFuelCalculator();
    }

    public function testSumsPositiveDeltasOfASingleChain(): void
    {
        $report = $this->calculator->calculate([[
            new CounterReading(1.0, odometerMeters: 1000, fuelUsedMilliliters: 500),
            new CounterReading(2.0, odometerMeters: 3000, fuelUsedMilliliters: 1750),
        ]]);

        self::assertEqualsWithDelta(2.0, $report->distanceKm, 1e-9);
        self::assertEqualsWithDelta(1.25, $report->fuelLitres, 1e-9);
    }

    public function testReturnsZeroForNoReadings(): void
    {
        $report = $this->calculator->calculate([]);

        self::assertEqualsWithDelta(0.0, $report->distanceKm, 1e-9);
        self::assertEqualsWithDelta(0.0, $report->fuelLitres, 1e-9);
    }

    public function testASingleReadingHasNoDelta(): void
    {
        $report = $this->calculator->calculate([[
            new CounterReading(1.0, odometerMeters: 1000, fuelUsedMilliliters: 500),
        ]]);

        self::assertEqualsWithDelta(0.0, $report->distanceKm, 1e-9);
        self::assertEqualsWithDelta(0.0, $report->fuelLitres, 1e-9);
    }

    public function testCounterResetIsSkippedAndChainRebaselines(): void
    {
        // 1000 -> 3000 (+2000), reset to 200, 200 -> 900 (+700) => 2700 m.
        $report = $this->calculator->calculate([[
            new CounterReading(1.0, odometerMeters: 1000),
            new CounterReading(2.0, odometerMeters: 3000),
            new CounterReading(3.0, odometerMeters: 200),
            new CounterReading(4.0, odometerMeters: 900),
        ]]);

        self::assertEqualsWithDelta(2.7, $report->distanceKm, 1e-9);
    }

    public function testFourByteRolloverIsTreatedAsAReset(): void
    {
        // Near uint32 max, wraps to a small value, then climbs 10 -> 60 (+50 m).
        $report = $this->calculator->calculate([[
            new CounterReading(1.0, odometerMeters: 4_294_967_290),
            new CounterReading(2.0, odometerMeters: 10),
            new CounterReading(3.0, odometerMeters: 60),
        ]]);

        self::assertEqualsWithDelta(0.05, $report->distanceKm, 1e-9);
    }

    public function testOdometerAndFuelPresenceAreTrackedIndependently(): void
    {
        // Odometer present on r1, r2; fuel present on r2, r3.
        $report = $this->calculator->calculate([[
            new CounterReading(1.0, odometerMeters: 100, fuelUsedMilliliters: null),
            new CounterReading(2.0, odometerMeters: 200, fuelUsedMilliliters: 50),
            new CounterReading(3.0, odometerMeters: null, fuelUsedMilliliters: 80),
        ]]);

        self::assertEqualsWithDelta(0.1, $report->distanceKm, 1e-9);   // 200 - 100 = 100 m
        self::assertEqualsWithDelta(0.03, $report->fuelLitres, 1e-9);  // 80 - 50 = 30 mL
    }

    public function testDeltasAreNeverTakenAcrossASegmentBoundary(): void
    {
        // Segment A: 1000 -> 1500 (+500). Segment B: 9000 -> 9200 (+200).
        // The 1500 -> 9000 jump between segments must NOT be counted.
        $report = $this->calculator->calculate([
            [
                new CounterReading(1.0, odometerMeters: 1000),
                new CounterReading(2.0, odometerMeters: 1500),
            ],
            [
                new CounterReading(3.0, odometerMeters: 9000),
                new CounterReading(4.0, odometerMeters: 9200),
            ],
        ]);

        self::assertEqualsWithDelta(0.7, $report->distanceKm, 1e-9); // 500 + 200 m
    }

    public function testReadingsAreOrderedByTimestampBeforeDifferencing(): void
    {
        // Provided out of order; sorted to 1000, 2000, 3000 => +2000 m.
        $report = $this->calculator->calculate([[
            new CounterReading(3.0, odometerMeters: 3000),
            new CounterReading(1.0, odometerMeters: 1000),
            new CounterReading(2.0, odometerMeters: 2000),
        ]]);

        self::assertEqualsWithDelta(2.0, $report->distanceKm, 1e-9);
    }

    public function testEqualConsecutiveValuesAddNothing(): void
    {
        $report = $this->calculator->calculate([[
            new CounterReading(1.0, odometerMeters: 1000),
            new CounterReading(2.0, odometerMeters: 1000),
            new CounterReading(3.0, odometerMeters: 1000),
        ]]);

        self::assertEqualsWithDelta(0.0, $report->distanceKm, 1e-9);
    }

    public function testAccumulatesInIntegersWithoutFloatDrift(): void
    {
        // 1001 readings, each +1 metre => exactly 1000 m = 1.0 km when summed
        // as integers and divided once. Summing per-delta floats (0.001 each)
        // would drift (proven: array_sum(0.001 x 1000) !== 1.0), so assertSame
        // locks in the "accumulate in integers, divide once" invariant.
        $readings = [];
        for ($i = 0; $i <= 1000; ++$i) {
            $readings[] = new CounterReading((float) $i, odometerMeters: $i);
        }

        $report = $this->calculator->calculate([$readings]);

        self::assertSame(1.0, $report->distanceKm);
    }

    public function testSubSecondTimestampsAreOrderedCorrectly(): void
    {
        // Realistic large epoch, one millisecond apart, supplied out of order.
        // Full sub-second precision sorts to 1000 -> 2000 -> 3000 (+2000 m).
        // If the timestamp lost sub-second precision (e.g. truncated to int
        // seconds) the readings would keep their input order and mis-compute.
        $report = $this->calculator->calculate([[
            new CounterReading(1781849860.550, odometerMeters: 3000),
            new CounterReading(1781849860.548, odometerMeters: 1000),
            new CounterReading(1781849860.549, odometerMeters: 2000),
        ]]);

        self::assertSame(2.0, $report->distanceKm);
    }
}
