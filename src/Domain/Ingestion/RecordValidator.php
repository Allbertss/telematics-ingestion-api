<?php

declare(strict_types=1);

namespace App\Domain\Ingestion;

/**
 * Validates and normalizes a single raw record (as decoded from JSON) into a
 * ValidRecord, or a Rejection when it cannot be used.
 *
 * "Valid" is defined narrowly: a record is rejected only on structural failure
 * — it is not an object, or it has no usable (finite, positive) timestamp. A
 * record that carries only context (e.g. ignition, no odometer) is valid.
 *
 * Field-level problems never reject the whole record: a known AVL value that
 * cannot be coerced to its expected type/range is simply dropped to null, and
 * unknown AVL parameters are kept verbatim in `extra`.
 */
final class RecordValidator
{
    /**
     * @var list<string> AVL ids that are interpreted into typed fields
     */
    private const array KNOWN_IO = ['24', '239', '240', '21', '216', '86', '231', '232'];

    public function validate(mixed $record): ValidRecord|Rejection
    {
        if (!is_array($record)) {
            return new Rejection('record is not an object');
        }

        $timestamp = $this->timestamp($record['timestamp'] ?? null);

        if (null === $timestamp) {
            return new Rejection('missing or non-finite timestamp');
        }

        $io = $record['io'] ?? [];

        if (!is_array($io)) {
            $io = [];
        }

        return new ValidRecord(
            timestamp: $timestamp,
            latitude: $this->coordinate($record['lat'] ?? null, 90.0),
            longitude: $this->coordinate($record['lon'] ?? null, 180.0),
            altitudeMeters: $this->signedInt($record['altitude'] ?? null),
            speedKmh: $this->unsignedInt($io['24'] ?? null),
            ignition: $this->flag($io['239'] ?? null),
            movement: $this->flag($io['240'] ?? null),
            gsmSignal: $this->boundedInt($io['21'] ?? null, 1, 5),
            odometerMeters: $this->unsignedInt($io['216'] ?? null),
            fuelUsedMilliliters: $this->unsignedInt($io['86'] ?? null),
            platePart1: $this->text($io['231'] ?? null),
            platePart2: $this->text($io['232'] ?? null),
            extra: $this->unknownParams($io),
        );
    }

    private function timestamp(mixed $value): ?float
    {
        if (!is_int($value) && !is_float($value)) {
            return null;
        }

        $float = (float) $value;

        return is_finite($float) && $float > 0.0 ? $float : null;
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

    private function unsignedInt(mixed $value): ?int
    {
        $int = $this->signedInt($value);

        return null !== $int && $int >= 0 ? $int : null;
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
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @param array<array-key, mixed> $io
     *
     * @return array<array-key, mixed>
     */
    private function unknownParams(array $io): array
    {
        $extra = [];

        foreach ($io as $id => $value) {
            if (!in_array((string) $id, self::KNOWN_IO, true)) {
                $extra[$id] = $value;
            }
        }

        return $extra;
    }
}
