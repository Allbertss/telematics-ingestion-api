<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Domain\Ingestion\RecordValidator;
use App\Domain\Ingestion\Rejection;
use App\Domain\Ingestion\ValidRecord;
use App\Domain\Vehicle\PlateParts;
use Doctrine\ORM\EntityManagerInterface;

final class RecordIngestionService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RecordValidator $validator,
        private readonly DeviceProvisioner $provisioner,
        private readonly RecordPersister $persister,
        private readonly PlateIngestor $plateIngestor,
    ) {
    }

    /**
     * @param list<mixed> $rawRecords
     */
    public function ingest(string $deviceIdentifier, array $rawRecords): IngestionSummary
    {
        $valid = [];
        $rejected = [];

        foreach ($rawRecords as $index => $rawRecord) {
            $outcome = $this->validator->validate($rawRecord);

            if ($outcome instanceof Rejection) {
                $rejected[] = new RejectedRecord($index, $outcome->reason);
            } else {
                $valid[] = $outcome;
            }
        }

        $stored = [] === $valid ? 0 : $this->store($deviceIdentifier, $valid);

        return new IngestionSummary(
            received: \count($rawRecords),
            stored: $stored,
            duplicates: \count($valid) - $stored,
            rejected: $rejected,
        );
    }

    /**
     * @param non-empty-list<ValidRecord> $valid
     */
    private function store(string $deviceIdentifier, array $valid): int
    {
        $stored = 0;

        $this->entityManager->wrapInTransaction(function () use ($deviceIdentifier, $valid, &$stored): void {
            $device = $this->provisioner->provision($deviceIdentifier, $this->earliest($valid));

            $this->entityManager->flush();

            $stored = $this->persister->persist($device, $valid);

            $this->plateIngestor->ingest($device, $this->plateReadings($valid));
            $this->entityManager->flush();
        });

        return $stored;
    }

    /**
     * @param non-empty-list<ValidRecord> $valid
     */
    private function earliest(array $valid): \DateTimeImmutable
    {
        $timestamps = array_map(static fn (ValidRecord $record): float => $record->timestamp, $valid);

        return EpochTime::toDateTime(min($timestamps));
    }

    /**
     * @param list<ValidRecord> $valid
     *
     * @return list<PlateParts>
     */
    private function plateReadings(array $valid): array
    {
        return array_map(
            static fn (ValidRecord $record): PlateParts => new PlateParts(
                $record->timestamp,
                $record->platePart1,
                $record->platePart2,
            ),
            $valid,
        );
    }
}
