<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TelematicsRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TelematicsRecordRepository::class)]
#[ORM\Table(name: 'telematics_record')]
#[ORM\UniqueConstraint(name: 'uniq_record_device_recorded_at', columns: ['device_id', 'recorded_at'])]
#[ORM\Index(name: 'idx_record_recorded_at_brin', columns: ['recorded_at'])]
class TelematicsRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    /**
     * @param array<array-key, mixed>|null $extra
     */
    public function __construct(
        #[ORM\ManyToOne(targetEntity: Device::class)]
        #[ORM\JoinColumn(name: 'device_id', nullable: false)]
        private Device $device,
        #[ORM\Column(name: 'recorded_at', type: Types::DATETIMETZ_IMMUTABLE)]
        private \DateTimeImmutable $recordedAt,
        #[ORM\Column(type: Types::FLOAT, nullable: true)]
        private ?float $latitude = null,
        #[ORM\Column(type: Types::FLOAT, nullable: true)]
        private ?float $longitude = null,
        #[ORM\Column(name: 'altitude_m', type: Types::INTEGER, nullable: true)]
        private ?int $altitudeMeters = null,
        #[ORM\Column(name: 'speed_kmh', type: Types::SMALLINT, nullable: true)]
        private ?int $speedKmh = null,
        #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
        private ?bool $ignition = null,
        #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
        private ?bool $movement = null,
        #[ORM\Column(name: 'gsm_signal', type: Types::SMALLINT, nullable: true)]
        private ?int $gsmSignal = null,
        #[ORM\Column(name: 'total_odometer_m', type: Types::BIGINT, nullable: true)]
        private ?int $totalOdometerMeters = null,
        #[ORM\Column(name: 'engine_total_fuel_used_ml', type: Types::BIGINT, nullable: true)]
        private ?int $engineTotalFuelUsedMilliliters = null,
        #[ORM\Column(type: Types::JSON, nullable: true, options: ['jsonb' => true])]
        private ?array $extra = null,
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

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function getAltitudeMeters(): ?int
    {
        return $this->altitudeMeters;
    }

    public function getSpeedKmh(): ?int
    {
        return $this->speedKmh;
    }

    public function getIgnition(): ?bool
    {
        return $this->ignition;
    }

    public function getMovement(): ?bool
    {
        return $this->movement;
    }

    public function getGsmSignal(): ?int
    {
        return $this->gsmSignal;
    }

    public function getTotalOdometerMeters(): ?int
    {
        return $this->totalOdometerMeters;
    }

    public function getEngineTotalFuelUsedMilliliters(): ?int
    {
        return $this->engineTotalFuelUsedMilliliters;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function getExtra(): ?array
    {
        return $this->extra;
    }
}
