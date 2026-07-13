<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Entity\Device;
use Doctrine\ORM\EntityManagerInterface;

final class DeviceProvisioner
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function provision(string $identifier, \DateTimeImmutable $firstSeenAt): Device
    {
        $device = $this->entityManager
            ->getRepository(Device::class)
            ->findOneBy(['identifier' => $identifier]);

        if (null !== $device) {
            return $device;
        }

        $device = new Device($identifier, $firstSeenAt);

        $this->entityManager->persist($device);

        return $device;
    }
}
