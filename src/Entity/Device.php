<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DeviceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A physical telematics unit Teltonika FMC650, identified by the
 * stable identifier it sends in the ingestion envelope. Auto-provisioned on
 * first sighting. Records belong to a device; the vehicle is resolved
 * separately from plate observations.
 */
#[ORM\Entity(repositoryClass: DeviceRepository::class)]
#[ORM\Table(name: 'device')]
class Device
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $identifier;

    #[ORM\Column(name: 'first_seen_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $firstSeenAt;

    public function __construct(string $identifier, \DateTimeImmutable $firstSeenAt)
    {
        $this->identifier = $identifier;
        $this->firstSeenAt = $firstSeenAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getFirstSeenAt(): \DateTimeImmutable
    {
        return $this->firstSeenAt;
    }
}
