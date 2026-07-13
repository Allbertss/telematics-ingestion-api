<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Entity\Device;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

final class DeviceProvisioner
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function provision(string $identifier, \DateTimeImmutable $firstSeenAt): Device
    {
        $device = $this->find($identifier);

        if (null !== $device) {
            return $device;
        }

        $this->entityManager->getConnection()->executeStatement(
            'INSERT INTO device (identifier, first_seen_at) VALUES (:identifier, CAST(:first_seen_at AS timestamptz)) ON CONFLICT (identifier) DO NOTHING',
            [
                'identifier' => $identifier,
                'first_seen_at' => $firstSeenAt->format('Y-m-d H:i:s.uP'),
            ],
            [
                'identifier' => ParameterType::STRING,
                'first_seen_at' => ParameterType::STRING,
            ],
        );

        $device = $this->find($identifier);

        if (null === $device) {
            throw new \RuntimeException(sprintf('Failed to provision device "%s".', $identifier));
        }

        return $device;
    }

    private function find(string $identifier): ?Device
    {
        return $this->entityManager->getRepository(Device::class)->findOneBy(['identifier' => $identifier]);
    }
}
