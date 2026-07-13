<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Vehicle\PlateParts;
use App\Repository\DevicePlateObservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DevicePlateObservationRepository::class)]
#[ORM\Table(name: 'device_plate_observation')]
#[ORM\Index(name: 'idx_plate_observation_plate', columns: ['plate'])]
#[ORM\Index(name: 'idx_plate_observation_device_observed_at', columns: ['device_id', 'observed_at'])]
class DevicePlateObservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: Device::class)]
        #[ORM\JoinColumn(name: 'device_id', nullable: false)]
        private Device $device,
        #[ORM\Column(length: 2 * PlateParts::MAX_LENGTH)]
        private string $plate,
        #[ORM\Column(name: 'observed_at', type: Types::DATETIMETZ_IMMUTABLE)]
        private \DateTimeImmutable $observedAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDevice(): Device
    {
        return $this->device;
    }

    public function getPlate(): string
    {
        return $this->plate;
    }

    public function getObservedAt(): \DateTimeImmutable
    {
        return $this->observedAt;
    }
}
