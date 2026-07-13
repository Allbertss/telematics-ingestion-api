<?php

declare(strict_types=1);

namespace App\Tests\Ingestion;

use App\Domain\Ingestion\ValidRecord;
use App\Entity\Device;
use App\Entity\TelematicsRecord;
use App\Ingestion\RecordPersister;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RecordPersisterTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Connection $connection;
    private RecordPersister $persister;
    private Device $device;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->connection = $this->em->getConnection();
        $this->persister = new RecordPersister($this->connection);

        $this->device = new Device('356938035643809', new \DateTimeImmutable('2026-07-13T10:00:00+00:00'));
        $this->em->persist($this->device);
        $this->em->flush();
    }

    public function testStoresValidRecords(): void
    {
        $stored = $this->persister->persist($this->device, [
            $this->record(1781849860.548, odometer: 1000),
            $this->record(1781849861.548, odometer: 1100),
        ]);

        self::assertSame(2, $stored);
        self::assertSame(2, $this->countRecords());
    }

    public function testSkipsDuplicatesOnResend(): void
    {
        $batch = [
            $this->record(1781849860.548, odometer: 1000),
            $this->record(1781849861.548, odometer: 1100),
        ];

        self::assertSame(2, $this->persister->persist($this->device, $batch));
        // A buffered device resends the same batch after a GSM gap.
        self::assertSame(0, $this->persister->persist($this->device, $batch));
        self::assertSame(2, $this->countRecords());
    }

    public function testSkipsDuplicatesWithinASingleBatch(): void
    {
        $stored = $this->persister->persist($this->device, [
            $this->record(1781849860.548, odometer: 1000),
            $this->record(1781849860.548, odometer: 1000), // same (device, timestamp)
        ]);

        self::assertSame(1, $stored);
        self::assertSame(1, $this->countRecords());
    }

    public function testRawInsertPreservesSubSecondTimestampAndBigCounter(): void
    {
        $this->persister->persist($this->device, [
            $this->record(1781849860.548, odometer: 4_000_000_123), // odometer exceeds int4
        ]);

        $this->em->clear();
        $record = $this->em->getRepository(TelematicsRecord::class)->findOneBy([]);
        self::assertInstanceOf(TelematicsRecord::class, $record);

        self::assertSame(4_000_000_123, $record->getTotalOdometerMeters());
        self::assertSame(
            gmdate('Y-m-d H:i:s', 1781849860).'.548000',
            $record->getRecordedAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        );
    }

    private function record(float $timestamp, ?int $odometer = null): ValidRecord
    {
        return new ValidRecord(
            timestamp: $timestamp,
            latitude: null,
            longitude: null,
            altitudeMeters: null,
            speedKmh: null,
            ignition: null,
            movement: null,
            gsmSignal: null,
            odometerMeters: $odometer,
            fuelUsedMilliliters: null,
            platePart1: null,
            platePart2: null,
            extra: [],
        );
    }

    private function countRecords(): int
    {
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM telematics_record');

        return is_numeric($count) ? (int) $count : 0;
    }
}
