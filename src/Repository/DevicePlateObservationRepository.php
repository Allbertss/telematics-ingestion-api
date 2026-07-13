<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Device;
use App\Entity\DevicePlateObservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
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

    /**
     * The device's most recently observed plate, or null if none logged yet.
     */
    public function findLatestPlate(Device $device): ?string
    {
        $plate = $this->createQueryBuilder('o')
            ->select('o.plate')
            ->andWhere('o.device = :device')
            ->setParameter('device', $device)
            ->orderBy('o.observedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_SCALAR_COLUMN);

        return is_string($plate) ? $plate : null;
    }
}
