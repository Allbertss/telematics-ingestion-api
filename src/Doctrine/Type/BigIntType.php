<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\BigIntType as BaseBigIntType;

final class BigIntType extends BaseBigIntType
{
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?int
    {
        // Delegate to the parent for the driver-value handling/validation, then
        // coerce its int|string result to a plain int (safe on 64-bit PHP).
        $value = parent::convertToPHPValue($value, $platform);

        return null === $value ? null : (int) $value;
    }
}
