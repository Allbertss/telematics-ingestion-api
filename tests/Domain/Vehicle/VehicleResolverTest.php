<?php

declare(strict_types=1);

namespace App\Tests\Domain\Vehicle;

use App\Domain\Vehicle\PlateObservation;
use App\Domain\Vehicle\VehicleResolver;
use PHPUnit\Framework\TestCase;

final class VehicleResolverTest extends TestCase
{
    private VehicleResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new VehicleResolver();
    }

    public function testReturnsNullWhenRecordPredatesAllObservations(): void
    {
        $plate = $this->resolver->resolvePlateAt([
            new PlateObservation('AB123', 100.0),
        ], 50.0);

        self::assertNull($plate);
    }

    public function testReturnsNullWhenThereAreNoObservations(): void
    {
        self::assertNull($this->resolver->resolvePlateAt([], 100.0));
    }

    public function testResolvesToAnObservationBeforeTheRecord(): void
    {
        $plate = $this->resolver->resolvePlateAt([
            new PlateObservation('AB123', 100.0),
        ], 150.0);

        self::assertSame('AB123', $plate);
    }

    public function testObservedAtEqualToTimestampResolvesInclusively(): void
    {
        $plate = $this->resolver->resolvePlateAt([
            new PlateObservation('AB123', 100.0),
        ], 100.0);

        self::assertSame('AB123', $plate);
    }

    public function testPicksGreatestObservedAtNotAfterTheTimestamp(): void
    {
        $plate = $this->resolver->resolvePlateAt([
            new PlateObservation('AA111', 100.0),
            new PlateObservation('BB222', 200.0),
            new PlateObservation('CC333', 300.0),
        ], 250.0);

        self::assertSame('BB222', $plate); // 200 <= 250 < 300
    }

    public function testResolvesToTheEarlierPlateBetweenTwoObservations(): void
    {
        $plate = $this->resolver->resolvePlateAt([
            new PlateObservation('AA111', 100.0),
            new PlateObservation('BB222', 200.0),
        ], 150.0);

        self::assertSame('AA111', $plate);
    }

    public function testResolutionIsIndependentOfObservationOrder(): void
    {
        $observations = [
            new PlateObservation('CC333', 300.0),
            new PlateObservation('AA111', 100.0),
            new PlateObservation('BB222', 200.0),
        ];

        self::assertSame('BB222', $this->resolver->resolvePlateAt($observations, 250.0));
    }

    public function testResolvesToTheLatestPlateWhenRecordIsAfterAll(): void
    {
        $plate = $this->resolver->resolvePlateAt([
            new PlateObservation('AA111', 100.0),
            new PlateObservation('BB222', 200.0),
        ], 999.0);

        self::assertSame('BB222', $plate);
    }

    public function testOnATieTheLastObservationInInputWins(): void
    {
        // Distinct plates sharing an observedAt should not happen per device
        // (timestamps are unique per device), but the rule is pinned so
        // resolution is deterministic if it ever does.
        $plate = $this->resolver->resolvePlateAt([
            new PlateObservation('AA111', 100.0),
            new PlateObservation('BB222', 100.0),
        ], 100.0);

        self::assertSame('BB222', $plate);
    }
}
