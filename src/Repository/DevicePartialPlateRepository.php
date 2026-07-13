<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DevicePartialPlate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
