<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DevicePlateObservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DevicePlateObservation>
 */
final class DevicePlateObservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DevicePlateObservation::class);
    }
}
