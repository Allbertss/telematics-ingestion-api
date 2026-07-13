<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Domain\Report\CounterReading;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class ReportingRepository
{
    private const string SEGMENTS_SQL = <<<'SQL'
        WITH windows AS (
            SELECT
                device_id,
                plate,
                observed_at AS window_start,
                LEAD(observed_at) OVER (PARTITION BY device_id ORDER BY observed_at) AS window_end
            FROM device_plate_observation
        )
        SELECT
            w.device_id AS device_id,
            EXTRACT(EPOCH FROM w.window_start) AS window_start,
            EXTRACT(EPOCH FROM r.recorded_at) AS ts,
            r.total_odometer_m AS odometer,
            r.engine_total_fuel_used_ml AS fuel
        FROM windows w
        JOIN telematics_record r
          ON r.device_id = w.device_id
         AND r.recorded_at >= GREATEST(w.window_start, CAST(:from AS timestamptz))
         AND r.recorded_at <  LEAST(COALESCE(w.window_end, 'infinity'::timestamptz), CAST(:to AS timestamptz))
        WHERE w.plate = :plate
        ORDER BY w.device_id, w.window_start, r.recorded_at
        SQL;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function plateExists(string $plate): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT 1 FROM device_plate_observation WHERE plate = :plate LIMIT 1',
            ['plate' => $plate],
        );
    }

    /**
     * @return list<list<CounterReading>>
     */
    public function segments(string $plate, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->connection->fetchAllAssociative(
            self::SEGMENTS_SQL,
            [
                'plate' => $plate,
                'from' => $from->format('Y-m-d H:i:s.uP'),
                'to' => $to->format('Y-m-d H:i:s.uP'),
            ],
            [
                'plate' => ParameterType::STRING,
                'from' => ParameterType::STRING,
                'to' => ParameterType::STRING,
            ],
        );

        $segments = [];
        $current = [];
        $currentKey = null;

        foreach ($rows as $row) {
            $key = $this->int($row['device_id']).'|'.$this->float($row['window_start']);

            if ($key !== $currentKey) {
                if ([] !== $current) {
                    $segments[] = $current;
                }

                $current = [];
                $currentKey = $key;
            }

            $current[] = new CounterReading(
                $this->float($row['ts']),
                $this->intOrNull($row['odometer']),
                $this->intOrNull($row['fuel']),
            );
        }

        if ([] !== $current) {
            $segments[] = $current;
        }

        return $segments;
    }

    private function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function float(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
