<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Domain\Vehicle\PlateAssembler;
use App\Domain\Vehicle\PlateAssemblyState;
use App\Domain\Vehicle\PlateParts;
use App\Entity\Device;
use App\Entity\DevicePartialPlate;
use App\Entity\DevicePlateObservation;
use App\Repository\DevicePlateObservationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Applies a batch's plate halves to a device's persistent state: seeds the pure
 * PlateAssembler with the staged halves (DevicePartialPlate) and the last logged
 * plate, then writes back the updated staging and appends a
 * DevicePlateObservation for each newly changed plate.
 *
 * Does not flush — the caller owns the transaction.
 *
 * MVP note: seeding uses the device's latest logged plate, which is exact for
 * in-order and cross-batch half-completion. A whole batch arriving out of order
 * relative to newer ones may emit a redundant/omitted observation, but as-of
 * resolution over the append-only log stays correct.
 */
final class PlateIngestor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlateAssembler $assembler,
        private readonly DevicePlateObservationRepository $observations,
    ) {
    }

    /**
     * @param list<PlateParts> $readings
     */
    public function ingest(Device $device, array $readings): void
    {
        $staging = $this->entityManager
            ->getRepository(DevicePartialPlate::class)
            ->findOneBy(['device' => $device]);

        if (null === $staging) {
            $staging = new DevicePartialPlate($device);
            $this->entityManager->persist($staging);
        }

        $result = $this->assembler->accumulate($readings, new PlateAssemblyState(
            part1: $staging->getPart1(),
            part1At: EpochTime::toFloatOrNull($staging->getPart1At()),
            part2: $staging->getPart2(),
            part2At: EpochTime::toFloatOrNull($staging->getPart2At()),
            plate: $this->observations->findLatestPlate($device),
        ));

        $state = $result->state;

        if (null !== $state->part1 && null !== $state->part1At) {
            $staging->setPart1($state->part1, EpochTime::toDateTime($state->part1At));
        }

        if (null !== $state->part2 && null !== $state->part2At) {
            $staging->setPart2($state->part2, EpochTime::toDateTime($state->part2At));
        }

        foreach ($result->observations as $observation) {
            $this->entityManager->persist(new DevicePlateObservation(
                $device,
                $observation->plate,
                EpochTime::toDateTime($observation->observedAt),
            ));
        }
    }
}
