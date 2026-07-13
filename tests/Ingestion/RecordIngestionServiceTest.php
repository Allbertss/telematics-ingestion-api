<?php

declare(strict_types=1);

namespace App\Tests\Ingestion;

use App\Domain\Ingestion\RecordValidator;
use App\Domain\Vehicle\PlateAssembler;
use App\Entity\Device;
use App\Entity\DevicePlateObservation;
use App\Ingestion\DeviceProvisioner;
use App\Ingestion\PlateIngestor;
use App\Ingestion\RecordIngestionService;
use App\Ingestion\RecordPersister;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RecordIngestionServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RecordIngestionService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $plateRepository = $this->em->getRepository(DevicePlateObservation::class);
        $this->service = new RecordIngestionService(
            $this->em,
            new RecordValidator(),
            new DeviceProvisioner($this->em),
            new RecordPersister($this->em->getConnection()),
            new PlateIngestor($this->em, new PlateAssembler(), $plateRepository),
        );
    }

    public function testIngestsAValidBatchAndProvisionsTheDevice(): void
    {
        $summary = $this->service->ingest('356938035643809', [
            $this->rawRecord(1781849860.548, ['216' => 1000]),
            $this->rawRecord(1781849861.548, ['216' => 1100]),
        ]);

        self::assertSame(2, $summary->received);
        self::assertSame(2, $summary->stored);
        self::assertSame(0, $summary->duplicates);
        self::assertSame([], $summary->rejected);

        self::assertSame(2, $this->countRecords());
        self::assertNotNull(
            $this->em->getRepository(Device::class)->findOneBy(['identifier' => '356938035643809']),
        );
    }

    public function testQuarantinesInvalidRecordsButKeepsTheValidOnes(): void
    {
        $summary = $this->service->ingest('356938035643809', [
            $this->rawRecord(1781849860.548, ['216' => 1000]),
            ['io' => ['216' => 1100]], // no timestamp -> rejected
            $this->rawRecord(1781849862.548, ['216' => 1200]),
        ]);

        self::assertSame(3, $summary->received);
        self::assertSame(2, $summary->stored);
        self::assertCount(1, $summary->rejected);

        $rejection = $summary->rejected[0];
        self::assertSame(1, $rejection->index);
        self::assertStringContainsString('timestamp', $rejection->reason);

        self::assertSame(2, $this->countRecords());
    }

    public function testIsIdempotentWhenTheSameBatchIsResent(): void
    {
        $batch = [
            $this->rawRecord(1781849860.548, ['216' => 1000]),
            $this->rawRecord(1781849861.548, ['216' => 1100]),
        ];

        $this->service->ingest('356938035643809', $batch);
        $summary = $this->service->ingest('356938035643809', $batch);

        self::assertSame(0, $summary->stored);
        self::assertSame(2, $summary->duplicates);
        self::assertSame(2, $this->countRecords());
    }

    public function testAssemblesThePlateFromTheBatch(): void
    {
        $this->service->ingest('356938035643809', [
            $this->rawRecord(1781849860.548, ['216' => 1000, '231' => 'AB', '232' => '123']),
        ]);

        $plate = $this->em->getRepository(DevicePlateObservation::class)->findOneBy([]);
        self::assertInstanceOf(DevicePlateObservation::class, $plate);
        self::assertSame('AB123', $plate->getPlate());
    }

    /**
     * @param array<array-key, mixed> $io
     *
     * @return array<string, mixed>
     */
    private function rawRecord(float $timestamp, array $io = []): array
    {
        return ['timestamp' => $timestamp, 'io' => $io];
    }

    private function countRecords(): int
    {
        $count = $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM telematics_record');

        return is_numeric($count) ? (int) $count : 0;
    }
}
