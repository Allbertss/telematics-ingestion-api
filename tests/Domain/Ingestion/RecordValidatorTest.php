<?php

declare(strict_types=1);

namespace App\Tests\Domain\Ingestion;

use App\Domain\Ingestion\RecordValidator;
use App\Domain\Ingestion\Rejection;
use App\Domain\Ingestion\ValidRecord;
use PHPUnit\Framework\TestCase;

final class RecordValidatorTest extends TestCase
{
    private RecordValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RecordValidator();
    }

    public function testValidatesAFullRecord(): void
    {
        $result = $this->validator->validate([
            'timestamp' => 1781849860.548,
            'lat' => 40.17,
            'lon' => 44.49,
            'altitude' => 990,
            'io' => [
                '24' => 54,
                '239' => 1,
                '240' => 0,
                '21' => 4,
                '216' => 123456,
                '86' => 7890,
                '231' => 'AB',
                '232' => '123CD',
            ],
        ]);

        self::assertInstanceOf(ValidRecord::class, $result);
        self::assertSame(1781849860.548, $result->timestamp);
        self::assertSame(40.17, $result->latitude);
        self::assertSame(44.49, $result->longitude);
        self::assertSame(990, $result->altitudeMeters);
        self::assertSame(54, $result->speedKmh);
        self::assertTrue($result->ignition);
        self::assertFalse($result->movement);
        self::assertSame(4, $result->gsmSignal);
        self::assertSame(123456, $result->odometerMeters);
        self::assertSame(7890, $result->fuelUsedMilliliters);
        self::assertSame('AB', $result->platePart1);
        self::assertSame('123CD', $result->platePart2);
        self::assertSame([], $result->extra);
    }

    public function testMissingTimestampIsRejected(): void
    {
        $result = $this->validator->validate(['io' => ['216' => 123]]);

        self::assertInstanceOf(Rejection::class, $result);
        self::assertStringContainsString('timestamp', $result->reason);
    }

    public function testNonNumericTimestampIsRejected(): void
    {
        $result = $this->validator->validate(['timestamp' => 'not-a-number']);

        self::assertInstanceOf(Rejection::class, $result);
    }

    public function testNonFiniteTimestampIsRejected(): void
    {
        $result = $this->validator->validate(['timestamp' => NAN]);

        self::assertInstanceOf(Rejection::class, $result);
    }

    public function testNonPositiveTimestampIsRejected(): void
    {
        $result = $this->validator->validate(['timestamp' => 0]);

        self::assertInstanceOf(Rejection::class, $result);
    }

    public function testNonArrayRecordIsRejected(): void
    {
        $result = $this->validator->validate('garbage');

        self::assertInstanceOf(Rejection::class, $result);
    }

    public function testContextOnlyRecordIsValid(): void
    {
        $result = $this->validator->validate([
            'timestamp' => 1000.0,
            'io' => ['239' => 1, '240' => 1],
        ]);

        self::assertInstanceOf(ValidRecord::class, $result);
        self::assertTrue($result->ignition);
        self::assertTrue($result->movement);
        self::assertNull($result->odometerMeters);
        self::assertNull($result->fuelUsedMilliliters);
    }

    public function testUnknownParamsAreKeptInExtra(): void
    {
        $result = $this->validator->validate([
            'timestamp' => 1000.0,
            'io' => ['216' => 5, '999' => 'x', '1000' => [1, 2]],
        ]);

        self::assertInstanceOf(ValidRecord::class, $result);
        self::assertSame(5, $result->odometerMeters);
        self::assertSame([999 => 'x', 1000 => [1, 2]], $result->extra);
    }

    public function testCoercesIntegralFloatAndIntegerStringToInt(): void
    {
        $result = $this->validator->validate([
            'timestamp' => 1000.0,
            'io' => ['216' => 123.0, '86' => '7890'],
        ]);

        self::assertInstanceOf(ValidRecord::class, $result);
        self::assertSame(123, $result->odometerMeters);
        self::assertSame(7890, $result->fuelUsedMilliliters);
    }

    public function testMalformedKnownValueIsDroppedNotRejected(): void
    {
        $result = $this->validator->validate([
            'timestamp' => 1000.0,
            'lat' => 40.17, // anchor payload so the record isn't empty after the drops
            'io' => ['216' => 'abc', '86' => 4.5], // non-integer values
        ]);

        self::assertInstanceOf(ValidRecord::class, $result);
        self::assertNull($result->odometerMeters);
        self::assertNull($result->fuelUsedMilliliters);
    }

    public function testNegativeCounterIsDropped(): void
    {
        $result = $this->validator->validate([
            'timestamp' => 1000.0,
            'lat' => 40.17,
            'io' => ['216' => -5],
        ]);

        self::assertInstanceOf(ValidRecord::class, $result);
        self::assertNull($result->odometerMeters);
    }

    public function testGsmSignalOutOfRangeFallsThroughToExtra(): void
    {
        $result = $this->validator->validate([
            'timestamp' => 1000.0,
            'io' => ['21' => 9], // beyond the 0..5 range
        ]);

        self::assertInstanceOf(ValidRecord::class, $result);
        self::assertNull($result->gsmSignal);        // not stored in the typed column...
        self::assertSame([21 => 9], $result->extra); // ...but preserved for inspection
    }

    public function testAcceptsAZeroGsmSignal(): void
    {
        // 0 ("no signal") is a legitimate reading, not out of range.
        $result = $this->validator->validate(['timestamp' => 1000.0, 'io' => ['21' => 0]]);

        self::assertInstanceOf(ValidRecord::class, $result);
        self::assertSame(0, $result->gsmSignal);
    }

    public function testIgnitionMapsZeroAndOneAndDropsOthers(): void
    {
        $on = $this->validator->validate(['timestamp' => 1000.0, 'io' => ['239' => 1]]);
        $off = $this->validator->validate(['timestamp' => 1000.0, 'io' => ['239' => 0]]);
        $bad = $this->validator->validate(['timestamp' => 1000.0, 'lat' => 40.17, 'io' => ['239' => 2]]);

        self::assertInstanceOf(ValidRecord::class, $on);
        self::assertInstanceOf(ValidRecord::class, $off);
        self::assertInstanceOf(ValidRecord::class, $bad);
        self::assertTrue($on->ignition);
        self::assertFalse($off->ignition);
        self::assertNull($bad->ignition);
    }

    public function testOutOfRangeCoordinatesAreDropped(): void
    {
        $result = $this->validator->validate([
            'timestamp' => 1000.0,
            'lat' => 200.0,
            'lon' => -400.0,
            'io' => ['216' => 1000], // anchor payload; the coordinates are dropped
        ]);

        self::assertInstanceOf(ValidRecord::class, $result);
        self::assertNull($result->latitude);
        self::assertNull($result->longitude);
    }

    public function testMissingIoYieldsNullContextButValidRecord(): void
    {
        $result = $this->validator->validate(['timestamp' => 1000.0, 'lat' => 40.17]);

        self::assertInstanceOf(ValidRecord::class, $result);
        self::assertNull($result->speedKmh);
        self::assertNull($result->odometerMeters);
        self::assertSame([], $result->extra);
    }

    public function testIoThatIsNotAnArrayIsTreatedAsEmpty(): void
    {
        $result = $this->validator->validate(['timestamp' => 1000.0, 'lat' => 40.17, 'io' => 'garbage']);

        self::assertInstanceOf(ValidRecord::class, $result);
        self::assertNull($result->odometerMeters);
        self::assertSame([], $result->extra);
    }

    public function testRejectsARecordWithNoUsableDataBeyondTheTimestamp(): void
    {
        $result = $this->validator->validate(['timestamp' => 1000.0]);

        self::assertInstanceOf(Rejection::class, $result);
    }

    public function testDropsAPlatePartThatWouldOverflowItsColumn(): void
    {
        $result = $this->validator->validate([
            'timestamp' => 1000.0,
            'io' => ['231' => str_repeat('A', 40), '232' => '123'], // part1 is 40 chars (> 32)
        ]);

        self::assertInstanceOf(ValidRecord::class, $result);
        self::assertNull($result->platePart1);   // dropped, not stored
        self::assertSame('123', $result->platePart2);
    }

    public function testRejectsATimestampBeyondTheSupportedRange(): void
    {
        $result = $this->validator->validate(['timestamp' => 1e15]); // year ~31,000,000

        self::assertInstanceOf(Rejection::class, $result);
    }

    public function testCapsSpeedToTheTwoByteUnsignedRange(): void
    {
        // 65535 (full 2-byte unsigned max) is valid and stored...
        $ok = $this->validator->validate(['timestamp' => 1000.0, 'io' => ['24' => 65535]]);
        self::assertInstanceOf(ValidRecord::class, $ok);
        self::assertSame(65535, $ok->speedKmh);

        // ...beyond it is dropped, so it can't overflow the column and 500 the batch.
        $over = $this->validator->validate(['timestamp' => 1000.0, 'io' => ['24' => 70000, '216' => 1000]]);
        self::assertInstanceOf(ValidRecord::class, $over);
        self::assertNull($over->speedKmh);
    }

    public function testCapsOdometerAndFuelToTheFourByteUnsignedRange(): void
    {
        $ok = $this->validator->validate(['timestamp' => 1000.0, 'io' => ['216' => 4294967295, '86' => 4294967295]]);
        self::assertInstanceOf(ValidRecord::class, $ok);
        self::assertSame(4294967295, $ok->odometerMeters);
        self::assertSame(4294967295, $ok->fuelUsedMilliliters);

        $over = $this->validator->validate(['timestamp' => 1000.0, 'io' => ['216' => 5000000000, '24' => 50]]);
        self::assertInstanceOf(ValidRecord::class, $over);
        self::assertNull($over->odometerMeters);            // too wide for the column...
        self::assertSame([216 => 5000000000], $over->extra); // ...but not silently lost
    }

    public function testDropsAnAltitudeBeyondItsColumnRange(): void
    {
        $result = $this->validator->validate(['timestamp' => 1000.0, 'lat' => 40.17, 'altitude' => 3000000000]); // > int4 max

        self::assertInstanceOf(ValidRecord::class, $result);
        self::assertNull($result->altitudeMeters);
    }
}
