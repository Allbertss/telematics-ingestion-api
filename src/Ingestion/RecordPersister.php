<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Domain\Ingestion\ValidRecord;
use App\Entity\Device;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class RecordPersister
{
    private const string INSERT_SQL = <<<'SQL'
        INSERT INTO telematics_record
            (device_id, recorded_at, latitude, longitude, altitude_m, speed_kmh,
             ignition, movement, gsm_signal, total_odometer_m,
             engine_total_fuel_used_ml, extra)
        VALUES
            (:device_id, CAST(:recorded_at AS timestamptz),
             CAST(:latitude AS double precision), CAST(:longitude AS double precision),
             :altitude_m, :speed_kmh, :ignition, :movement, :gsm_signal,
             :total_odometer_m, :engine_total_fuel_used_ml, CAST(:extra AS jsonb))
        ON CONFLICT (device_id, recorded_at) DO NOTHING
        SQL;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param list<ValidRecord> $records
     *
     * @return int the number of rows stored (the rest were duplicates)
     */
    public function persist(Device $device, array $records): int
    {
        $deviceId = $device->getId();

        if (null === $deviceId) {
            throw new \InvalidArgumentException('The device must be persisted before its records.');
        }

        $stored = 0;

        foreach ($records as $record) {
            $stored += $this->insert($deviceId, $record);
        }

        return $stored;
    }

    private function insert(int $deviceId, ValidRecord $record): int
    {
        return (int) $this->connection->executeStatement(
            self::INSERT_SQL,
            [
                'device_id' => $deviceId,
                'recorded_at' => EpochTime::toDateTime($record->timestamp)->format('Y-m-d H:i:s.uP'),
                'latitude' => $record->latitude,
                'longitude' => $record->longitude,
                'altitude_m' => $record->altitudeMeters,
                'speed_kmh' => $record->speedKmh,
                'ignition' => $record->ignition,
                'movement' => $record->movement,
                'gsm_signal' => $record->gsmSignal,
                'total_odometer_m' => $record->odometerMeters,
                'engine_total_fuel_used_ml' => $record->fuelUsedMilliliters,
                'extra' => $this->encodeExtra($record->extra),
            ],
            [
                'device_id' => ParameterType::INTEGER,
                'recorded_at' => ParameterType::STRING,
                'latitude' => ParameterType::STRING,
                'longitude' => ParameterType::STRING,
                'altitude_m' => ParameterType::INTEGER,
                'speed_kmh' => ParameterType::INTEGER,
                'ignition' => ParameterType::BOOLEAN,
                'movement' => ParameterType::BOOLEAN,
                'gsm_signal' => ParameterType::INTEGER,
                'total_odometer_m' => ParameterType::INTEGER,
                'engine_total_fuel_used_ml' => ParameterType::INTEGER,
                'extra' => ParameterType::STRING,
            ],
        );
    }

    /**
     * @param array<array-key, mixed> $extra
     */
    private function encodeExtra(array $extra): ?string
    {
        if ([] === $extra) {
            return null;
        }

        $encoded = json_encode($extra);

        return false === $encoded ? null : $encoded;
    }
}
