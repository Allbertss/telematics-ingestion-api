<?php

declare(strict_types=1);

namespace App\Tests\Ingestion;

use App\Domain\Vehicle\PlateAssembler;
use App\Domain\Vehicle\PlateParts;
use App\Entity\Device;
use App\Entity\DevicePartialPlate;
use App\Entity\DevicePlateObservation;
use App\Ingestion\PlateIngestor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PlateIngestorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PlateIngestor $ingestor;
    private Device $device;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);

        $observations = $this->em->getRepository(DevicePlateObservation::class);
        $partialPlates = $this->em->getRepository(DevicePartialPlate::class);
        $this->ingestor = new PlateIngestor($this->em, new PlateAssembler(), $partialPlates, $observations);

        $this->device = new Device('356938035643809', new \DateTimeImmutable('2026-07-13T10:00:00+00:00'));
        $this->em->persist($this->device);
        $this->em->flush();
    }

    public function testCommitsAPlateWhenBothHalvesArriveAcrossTwoBatches(): void
    {
        // Batch 1: only the first half — nothing to commit yet.
        $this->ingestor->ingest($this->device, [new PlateParts(1781849860.0, part1: 'AB')]);
        $this->em->flush();
        self::assertSame([], $this->observedPlates());

        // Batch 2 (later): the second half completes the plate.
        $this->ingestor->ingest($this->device, [new PlateParts(1781849870.0, part2: '123')]);
        $this->em->flush();

        self::assertSame(['AB123'], $this->observedPlates());
    }

    public function testDoesNotDuplicateObservationWhenABatchIsResent(): void
    {
        $batch = [new PlateParts(1781849860.0, part1: 'AB', part2: '123')];

        $this->ingestor->ingest($this->device, $batch);
        $this->em->flush();
        $this->ingestor->ingest($this->device, $batch); // buffered resend
        $this->em->flush();

        self::assertSame(['AB123'], $this->observedPlates());
    }

    public function testEmitsANewObservationWhenThePlateChanges(): void
    {
        $this->ingestor->ingest($this->device, [new PlateParts(1781849860.0, part1: 'AB', part2: '123')]);
        $this->em->flush();
        $this->ingestor->ingest($this->device, [new PlateParts(1781849870.0, part1: 'XY')]);
        $this->em->flush();

        self::assertSame(['AB123', 'XY123'], $this->observedPlates());
    }

    /**
     * @return list<string>
     */
    private function observedPlates(): array
    {
        $observations = $this->em->getRepository(DevicePlateObservation::class)
            ->findBy(['device' => $this->device], ['observedAt' => 'ASC']);

        return array_map(static fn (DevicePlateObservation $o): string => $o->getPlate(), $observations);
    }
}
