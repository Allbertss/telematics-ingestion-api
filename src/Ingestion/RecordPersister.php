<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Domain\Ingestion\ValidRecord;
use App\Entity\Device;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class RecordPersister
{
    private const int MAX_ROWS_PER_INSERT = 1000;

    /**
     * @var list<array{string, string, ParameterType}>
     */
    private const array COLUMNS = [
        ['device_id', '?', ParameterType::INTEGER],
        ['recorded_at', 'CAST(? AS timestamptz)', ParameterType::STRING],
        ['latitude', 'CAST(? AS double precision)', ParameterType::STRING],
        ['longitude', 'CAST(? AS double precision)', ParameterType::STRING],
        ['altitude_m', '?', ParameterType::INTEGER],
        ['speed_kmh', '?', ParameterType::INTEGER],
        ['ignition', '?', ParameterType::BOOLEAN],
        ['movement', '?', ParameterType::BOOLEAN],
        ['gsm_signal', '?', ParameterType::INTEGER],
        ['total_odometer_m', '?', ParameterType::INTEGER],
        ['engine_total_fuel_used_ml', '?', ParameterType::INTEGER],
        ['extra', 'CAST(? AS jsonb)', ParameterType::STRING],
    ];

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

        foreach (array_chunk($records, self::MAX_ROWS_PER_INSERT) as $chunk) {
            $stored += $this->insertChunk($deviceId, $chunk);
        }

        return $stored;
    }

    /**
     * @param list<ValidRecord> $records
     */
    private function insertChunk(int $deviceId, array $records): int
    {
        if ([] === $records) {
            return 0;
        }

        $row = '('.implode(', ', array_column(self::COLUMNS, 1)).')';
        $rowTypes = array_column(self::COLUMNS, 2);

        $params = [];
        $types = [];

        foreach ($records as $record) {
            array_push($params, ...$this->rowValues($deviceId, $record));
            array_push($types, ...$rowTypes);
        }

        $sql = sprintf(
            'INSERT INTO telematics_record (%s) VALUES %s ON CONFLICT (device_id, recorded_at) DO NOTHING',
            implode(', ', array_column(self::COLUMNS, 0)),
            implode(', ', array_fill(0, count($records), $row)),
        );

        return (int) $this->connection->executeStatement($sql, $params, $types);
    }

    /**
     * One record's bound values, in the column order of {@see COLUMNS}.
     *
     * @return list<mixed>
     */
    private function rowValues(int $deviceId, ValidRecord $record): array
    {
        return [
            $deviceId,
            EpochTime::toDateTime($record->timestamp)->format('Y-m-d H:i:s.uP'),
            $record->latitude,
            $record->longitude,
            $record->altitudeMeters,
            $record->speedKmh,
            $record->ignition,
            $record->movement,
            $record->gsmSignal,
            $record->odometerMeters,
            $record->fuelUsedMilliliters,
            $this->encodeExtra($record->extra),
        ];
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
