<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TelematicsRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TelematicsRecord>
 */
final class TelematicsRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TelematicsRecord::class);
    }
}
