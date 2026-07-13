<?php

declare(strict_types=1);

namespace App\Domain\Ingestion;

use App\Domain\Vehicle\PlateParts;

final class RecordValidator
{
    private const int MAX_EPOCH_SECONDS = 253402300799; // 9999-12-31T23:59:59 UTC
    private const int MAX_UINT16 = 65535;
    private const int MAX_UINT32 = 4294967295;
    private const int MIN_INT32 = -2147483648;
    private const int MAX_INT32 = 2147483647;
    private const int MAX_GSM_SIGNAL = 5; // 0-5

    public function validate(mixed $record): ValidRecord|Rejection
    {
        if (!is_array($record)) {
            return new Rejection('record is not an object');
        }

        $timestamp = $this->timestamp($record['timestamp'] ?? null);

        if (null === $timestamp) {
            return new Rejection('missing or invalid timestamp');
        }

        $io = $record['io'] ?? [];

        if (!is_array($io)) {
            $io = [];
        }

        $known = [
            '24' => $this->boundedInt($io['24'] ?? null, 0, self::MAX_UINT16),
            '239' => $this->flag($io['239'] ?? null),
            '240' => $this->flag($io['240'] ?? null),
            '21' => $this->boundedInt($io['21'] ?? null, 0, self::MAX_GSM_SIGNAL),
            '216' => $this->boundedInt($io['216'] ?? null, 0, self::MAX_UINT32),
            '86' => $this->boundedInt($io['86'] ?? null, 0, self::MAX_UINT32),
            '231' => $this->text($io['231'] ?? null),
            '232' => $this->text($io['232'] ?? null),
        ];

        $valid = new ValidRecord(
            timestamp: $timestamp,
            latitude: $this->coordinate($record['lat'] ?? null, 90.0),
            longitude: $this->coordinate($record['lon'] ?? null, 180.0),
            altitudeMeters: $this->boundedInt($record['altitude'] ?? null, self::MIN_INT32, self::MAX_INT32),
            speedKmh: $known['24'],
            ignition: $known['239'],
            movement: $known['240'],
            gsmSignal: $known['21'],
            odometerMeters: $known['216'],
            fuelUsedMilliliters: $known['86'],
            platePart1: $known['231'],
            platePart2: $known['232'],
            extra: $this->extra($io, $known),
        );

        return $valid->hasPayload() ? $valid : new Rejection('record has no usable data beyond its timestamp');
    }

    private function timestamp(mixed $value): ?float
    {
        if (!is_int($value) && !is_float($value)) {
            return null;
        }

        $float = (float) $value;

        return is_finite($float) && $float > 0.0 && $float <= self::MAX_EPOCH_SECONDS ? $float : null;
    }

    private function coordinate(mixed $value, float $absoluteMax): ?float
    {
        if (!is_int($value) && !is_float($value)) {
            return null;
        }

        $float = (float) $value;

        return is_finite($float) && abs($float) <= $absoluteMax ? $float : null;
    }

    private function signedInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && is_finite($value) && floor($value) === $value) {
            return (int) $value;
        }

        if (is_string($value) && 1 === preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        return null;
    }

    private function boundedInt(mixed $value, int $min, int $max): ?int
    {
        $int = $this->signedInt($value);

        return null !== $int && $int >= $min && $int <= $max ? $int : null;
    }

    private function flag(mixed $value): ?bool
    {
        return match ($this->signedInt($value)) {
            0 => false,
            1 => true,
            default => null,
        };
    }

    private function text(mixed $value): ?string
    {
        if (is_string($value)) {
            $text = $value;
        } elseif (is_int($value) || is_float($value)) {
            $text = (string) $value;
        } else {
            return null;
        }

        return mb_strlen(trim($text)) > PlateParts::MAX_LENGTH ? null : $text;
    }

    /**
     * @param array<array-key, mixed>                $io
     * @param array<array-key, int|bool|string|null> $known id => the typed value it produced
     *
     * @return array<array-key, mixed>
     */
    private function extra(array $io, array $known): array
    {
        $extra = [];

        foreach ($io as $id => $value) {
            $interpreted = array_key_exists($id, $known) && null !== $known[$id];

            if (!$interpreted) {
                $extra[$id] = $value;
            }
        }

        return $extra;
    }
}
