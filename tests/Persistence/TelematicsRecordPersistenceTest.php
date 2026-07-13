<?php

declare(strict_types=1);

namespace App\Tests\Persistence;

use App\Entity\Device;
use App\Entity\TelematicsRecord;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TelematicsRecordPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    public function testPersistsAndHydratesARecordThroughTheCustomTypes(): void
    {
        $device = new Device('356938035643809', new \DateTimeImmutable('2026-07-13T10:00:00+00:00'));
        $record = new TelematicsRecord(
            device: $device,
            recordedAt: new \DateTimeImmutable('2026-07-13T10:00:01.548000+00:00'),
            speedKmh: 54,
            ignition: true,
            totalOdometerMeters: 4_000_000_123, // exceeds a 4-byte int; exercises BIGINT
            engineTotalFuelUsedMilliliters: 7890,
            extra: ['999' => 'kept-as-is'],
        );

        $this->em->persist($device);
        $this->em->persist($record);
        $this->em->flush();

        $id = $record->getId();
        self::assertNotNull($id);

        // Force a fresh read from the database (not the identity map).
        $this->em->clear();
        $reloaded = $this->em->find(TelematicsRecord::class, $id);

        self::assertInstanceOf(TelematicsRecord::class, $reloaded);

        // BIGINT hydrates to a PHP int (not a string), with the large value intact.
        self::assertIsInt($reloaded->getId());
        self::assertIsInt($reloaded->getTotalOdometerMeters());
        self::assertSame(4_000_000_123, $reloaded->getTotalOdometerMeters());
        self::assertSame(7890, $reloaded->getEngineTotalFuelUsedMilliliters());

        // Typed context columns.
        self::assertSame(54, $reloaded->getSpeedKmh());
        self::assertTrue($reloaded->getIgnition());
        self::assertNull($reloaded->getMovement());

        // jsonb round-trips the unknown params verbatim.
        self::assertSame(['999' => 'kept-as-is'], $reloaded->getExtra());

        // Sub-second precision survives (timestamp(6)); compare the UTC instant.
        $utc = $reloaded->getRecordedAt()->setTimezone(new \DateTimeZone('UTC'));
        self::assertSame('2026-07-13 10:00:01.548000', $utc->format('Y-m-d H:i:s.u'));
    }

    public function testTheDatabaseIsRolledBackBetweenTests(): void
    {
        // The record persisted by the other test must not leak into this one,
        // proving dama wraps each test in a rolled-back transaction.
        $count = $this->em->getRepository(TelematicsRecord::class)->count([]);

        self::assertSame(0, $count);
    }
}
