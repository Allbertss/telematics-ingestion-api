<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Device;
use App\Entity\DevicePartialPlate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DevicePartialPlate>
 */
final class DevicePartialPlateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DevicePartialPlate::class);
    }

    public function getOrCreate(Device $device): DevicePartialPlate
    {
        $staging = $this->findOneBy(['device' => $device]);

        if (null !== $staging) {
            return $staging;
        }

        $this->getEntityManager()->getConnection()->executeStatement(
            'INSERT INTO device_partial_plate (device_id) VALUES (:device_id) ON CONFLICT (device_id) DO NOTHING',
            ['device_id' => $device->getId()],
            ['device_id' => ParameterType::INTEGER],
        );

        $staging = $this->findOneBy(['device' => $device]);

        if (null === $staging) {
            throw new \RuntimeException('Failed to create plate staging for the device.');
        }

        return $staging;
    }
}
