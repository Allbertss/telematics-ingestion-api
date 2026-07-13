<?php

declare(strict_types=1);

namespace App\Ingestion;

final class EpochTime
{
    public static function toDateTime(float $epochSeconds): \DateTimeImmutable
    {
        $dateTime = \DateTimeImmutable::createFromFormat('U.u', number_format($epochSeconds, 6, '.', ''));

        if (false === $dateTime) {
            throw new \InvalidArgumentException('Invalid epoch timestamp.');
        }

        return $dateTime->setTimezone(new \DateTimeZone('UTC'));
    }

    public static function toFloat(\DateTimeImmutable $dateTime): float
    {
        return (float) $dateTime->format('U.u');
    }

    public static function toFloatOrNull(?\DateTimeImmutable $dateTime): ?float
    {
        return null === $dateTime ? null : self::toFloat($dateTime);
    }
}
