<?php

declare(strict_types=1);

namespace App\Tests\Domain\Vehicle;

use App\Domain\Vehicle\PlateAssembler;
use App\Domain\Vehicle\PlateAssemblyState;
use App\Domain\Vehicle\PlateObservation;
use App\Domain\Vehicle\PlateParts;
use PHPUnit\Framework\TestCase;

final class PlateAssemblerTest extends TestCase
{
    private PlateAssembler $assembler;

    protected function setUp(): void
    {
        $this->assembler = new PlateAssembler();
    }

    public function testAssemblesBothHalvesFromOneRecord(): void
    {
        $observations = $this->assembler->assemble([
            new PlateParts(1.0, part1: 'AB', part2: '123CD'),
        ]);

        self::assertSame([['AB123CD', 1.0]], self::tuples($observations));
    }

    public function testAssemblesHalvesArrivingInSeparateRecords(): void
    {
        $observations = $this->assembler->assemble([
            new PlateParts(1.0, part1: 'AB'),
            new PlateParts(2.0, part2: '123'),
        ]);

        // Observed at the record that COMPLETED the plate (the later half).
        self::assertSame([['AB123', 2.0]], self::tuples($observations));
    }

    public function testDoesNotCommitAPlateFromASingleHalf(): void
    {
        $observations = $this->assembler->assemble([
            new PlateParts(1.0, part1: 'AB'),
            new PlateParts(2.0, part1: 'AB'),
        ]);

        self::assertSame([], self::tuples($observations));
    }

    public function testNewFirstHalfSupersedesAndReassembles(): void
    {
        $observations = $this->assembler->assemble([
            new PlateParts(1.0, part1: 'AB', part2: '123'),
            new PlateParts(2.0, part1: 'XY'),
        ]);

        self::assertSame([
            ['AB123', 1.0],
            ['XY123', 2.0],
        ], self::tuples($observations));
    }

    public function testDoesNotEmitDuplicateObservationForUnchangedPlate(): void
    {
        $observations = $this->assembler->assemble([
            new PlateParts(1.0, part1: 'AB', part2: '123'),
            new PlateParts(2.0, part1: 'AB', part2: '123'),
            new PlateParts(3.0, part2: '123'),
        ]);

        self::assertSame([['AB123', 1.0]], self::tuples($observations));
    }

    public function testTrimsAndUppercasesHalves(): void
    {
        $observations = $this->assembler->assemble([
            new PlateParts(1.0, part1: ' ab ', part2: '123cd'),
        ]);

        self::assertSame([['AB123CD', 1.0]], self::tuples($observations));
    }

    public function testTreatsEmptyOrWhitespaceHalfAsAbsent(): void
    {
        $observations = $this->assembler->assemble([
            new PlateParts(1.0, part1: '   ', part2: '123'), // part1 empty -> absent
            new PlateParts(2.0, part1: 'AB'),                // now completes
        ]);

        self::assertSame([['AB123', 2.0]], self::tuples($observations));
    }

    public function testOrdersReadingsByTimestamp(): void
    {
        $observations = $this->assembler->assemble([
            new PlateParts(2.0, part2: '123'),
            new PlateParts(1.0, part1: 'AB'),
        ]);

        self::assertSame([['AB123', 2.0]], self::tuples($observations));
    }

    public function testReEmitsWhenPlateReturnsAfterChanging(): void
    {
        $observations = $this->assembler->assemble([
            new PlateParts(1.0, part1: 'AB', part2: '123'),
            new PlateParts(2.0, part1: 'XY', part2: '999'),
            new PlateParts(3.0, part1: 'AB', part2: '123'),
        ]);

        self::assertSame([
            ['AB123', 1.0],
            ['XY999', 2.0],
            ['AB123', 3.0],
        ], self::tuples($observations));
    }

    public function testIgnoresRecordsWithNoPlateHalves(): void
    {
        $observations = $this->assembler->assemble([
            new PlateParts(1.0),
            new PlateParts(2.0, part1: 'AB', part2: '123'),
        ]);

        self::assertSame([['AB123', 2.0]], self::tuples($observations));
    }

    public function testTransientFrankensteinPlateIsAcceptedThenCorrected(): void
    {
        // Device moves A -> B; only part2 changes first, briefly combining A's
        // part1 with B's part2, then self-corrects when part1 catches up.
        // Documented MVP limitation (DESIGN section 10).
        $observations = $this->assembler->assemble([
            new PlateParts(1.0, part1: 'AA', part2: '111'),
            new PlateParts(2.0, part2: '222'), // AA + 222 (Frankenstein)
            new PlateParts(3.0, part1: 'BB'),  // BB + 222 (corrected)
        ]);

        self::assertSame([
            ['AA111', 1.0],
            ['AA222', 2.0],
            ['BB222', 3.0],
        ], self::tuples($observations));
    }

    public function testAccumulateCombinesAHalfFromPriorStateWithANewHalf(): void
    {
        // A prior batch left part1 staged; this batch brings part2.
        $result = $this->assembler->accumulate(
            [new PlateParts(2.0, part2: '123')],
            new PlateAssemblyState(part1: 'AB', part1At: 1.0),
        );

        self::assertSame([['AB123', 2.0]], self::tuples($result->observations));
        self::assertSame('AB', $result->state->part1);
        self::assertSame('123', $result->state->part2);
        self::assertSame('AB123', $result->state->plate);
    }

    public function testAccumulateDoesNotReEmitTheAlreadyKnownPlate(): void
    {
        $result = $this->assembler->accumulate(
            [new PlateParts(2.0, part1: 'AB', part2: '123')], // same plate resent
            new PlateAssemblyState(part1: 'AB', part1At: 1.0, part2: '123', part2At: 1.0, plate: 'AB123'),
        );

        self::assertSame([], self::tuples($result->observations));
        self::assertSame('AB123', $result->state->plate);
    }

    public function testAccumulateEmitsWhenTheBatchChangesThePlate(): void
    {
        $result = $this->assembler->accumulate(
            [new PlateParts(3.0, part1: 'XY')],
            new PlateAssemblyState(part1: 'AB', part1At: 1.0, part2: '123', part2At: 1.0, plate: 'AB123'),
        );

        self::assertSame([['XY123', 3.0]], self::tuples($result->observations));
        self::assertSame('XY', $result->state->part1);
        self::assertSame('XY123', $result->state->plate);
    }

    public function testAccumulateIgnoresAHalfOlderThanTheStoredOne(): void
    {
        // Out-of-order: an older part1 must not regress the newer stored one.
        $result = $this->assembler->accumulate(
            [new PlateParts(2.0, part1: 'AB')], // older than the stored part1At = 5.0
            new PlateAssemblyState(part1: 'XY', part1At: 5.0, part2: '123', part2At: 5.0, plate: 'XY123'),
        );

        self::assertSame([], self::tuples($result->observations));
        self::assertSame('XY', $result->state->part1);
    }

    /**
     * @param list<PlateObservation> $observations
     *
     * @return list<array{string, float}>
     */
    private static function tuples(array $observations): array
    {
        return array_map(
            static fn (PlateObservation $o): array => [$o->plate, $o->observedAt],
            $observations,
        );
    }
}
