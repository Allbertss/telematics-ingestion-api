<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Domain\Vehicle\PlateAssembler;
use App\Domain\Vehicle\PlateAssemblyState;
use App\Domain\Vehicle\PlateParts;
use App\Entity\Device;
use App\Entity\DevicePlateObservation;
use App\Repository\DevicePartialPlateRepository;
use App\Repository\DevicePlateObservationRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

final class PlateIngestor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PlateAssembler $assembler,
        private readonly DevicePartialPlateRepository $partialPlates,
        private readonly DevicePlateObservationRepository $observations,
    ) {
    }

    /**
     * @param list<PlateParts> $readings
     */
    public function ingest(Device $device, array $readings): void
    {
        $staging = $this->partialPlates->getOrCreate($device);
        $lastPlate = $this->observations->findLatestPlate($device);

        $result = $this->assembler->accumulate($readings, new PlateAssemblyState(
            part1: $staging->getPart1(),
            part1At: EpochTime::toFloatOrNull($staging->getPart1At()),
            part2: $staging->getPart2(),
            part2At: EpochTime::toFloatOrNull($staging->getPart2At()),
            plate: $lastPlate,
        ));

        $state = $result->state;

        if (null !== $state->part1 && null !== $state->part1At) {
            $staging->setPart1($state->part1, EpochTime::toDateTime($state->part1At));
        }

        if (null !== $state->part2 && null !== $state->part2At) {
            $staging->setPart2($state->part2, EpochTime::toDateTime($state->part2At));
        }

        $backdateTo = null === $lastPlate && [] !== $result->observations
            ? $this->earliestRecordEpoch($device)
            : null;

        foreach ($result->observations as $index => $observation) {
            $observedAt = 0 === $index && null !== $backdateTo
                ? min($backdateTo, $observation->observedAt)
                : $observation->observedAt;

            $this->entityManager->persist(new DevicePlateObservation(
                $device,
                $observation->plate,
                EpochTime::toDateTime($observedAt),
            ));
        }
    }

    private function earliestRecordEpoch(Device $device): ?float
    {
        $epoch = $this->entityManager->getConnection()->fetchOne(
            'SELECT EXTRACT(EPOCH FROM MIN(recorded_at)) FROM telematics_record WHERE device_id = :device_id',
            ['device_id' => $device->getId()],
            ['device_id' => ParameterType::INTEGER],
        );

        return is_numeric($epoch) ? (float) $epoch : null;
    }
}
