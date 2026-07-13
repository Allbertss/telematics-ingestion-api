<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DevicePartialPlateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DevicePartialPlateRepository::class)]
#[ORM\Table(name: 'device_partial_plate')]
#[ORM\UniqueConstraint(name: 'uniq_partial_plate_device', columns: ['device_id'])]
class DevicePartialPlate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Device::class)]
    #[ORM\JoinColumn(name: 'device_id', nullable: false)]
    private Device $device;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $part1 = null;

    #[ORM\Column(name: 'part1_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $part1At = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $part2 = null;

    #[ORM\Column(name: 'part2_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $part2At = null;

    public function __construct(Device $device)
    {
        $this->device = $device;
    }

    public function setPart1(string $part1, \DateTimeImmutable $observedAt): void
    {
        $this->part1 = $part1;
        $this->part1At = $observedAt;
    }

    public function setPart2(string $part2, \DateTimeImmutable $observedAt): void
    {
        $this->part2 = $part2;
        $this->part2At = $observedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDevice(): Device
    {
        return $this->device;
    }

    public function getPart1(): ?string
    {
        return $this->part1;
    }

    public function getPart1At(): ?\DateTimeImmutable
    {
        return $this->part1At;
    }

    public function getPart2(): ?string
    {
        return $this->part2;
    }

    public function getPart2At(): ?\DateTimeImmutable
    {
        return $this->part2At;
    }
}
