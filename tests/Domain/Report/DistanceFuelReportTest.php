<?php

declare(strict_types=1);

namespace App\Tests\Domain\Report;

use App\Domain\Report\DistanceFuelReport;
use PHPUnit\Framework\TestCase;

final class DistanceFuelReportTest extends TestCase
{
    public function testComputesFuelConsumptionPer100Km(): void
    {
        $report = new DistanceFuelReport(distanceKm: 200.0, fuelLitres: 12.0);

        $value = $report->fuelPer100Km();
        self::assertNotNull($value);
        self::assertEqualsWithDelta(6.0, $value, 1e-9); // 12 / 200 * 100
    }

    public function testConsumptionIsNullWhenNoDistanceTravelled(): void
    {
        $report = new DistanceFuelReport(distanceKm: 0.0, fuelLitres: 2.0);

        self::assertNull($report->fuelPer100Km());
    }

    public function testConsumptionIsZeroWhenNoFuelConsumed(): void
    {
        $report = new DistanceFuelReport(distanceKm: 50.0, fuelLitres: 0.0);

        self::assertSame(0.0, $report->fuelPer100Km());
    }
}
