<?php

declare(strict_types=1);

namespace App\Tests\Reporting;

use App\Domain\Report\DistanceFuelCalculator;
use App\Entity\Device;
use App\Entity\DevicePlateObservation;
use App\Entity\TelematicsRecord;
use App\Reporting\ReportingRepository;
use App\Reporting\VehicleReportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class VehicleReportServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private VehicleReportService $service;
    private Device $device;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->service = new VehicleReportService(
            new ReportingRepository($this->em->getConnection()),
            new DistanceFuelCalculator(),
        );

        $this->device = new Device('356938035643809', new \DateTimeImmutable('2026-07-13T09:00:00+00:00'));
        $this->em->persist($this->device);
    }

    public function testComputesDistanceAndFuelForAPlate(): void
    {
        $this->seedObservation('AB123', '10:00');
        $this->seedRecord('10:01', odometer: 1000, fuel: 500);
        $this->seedRecord('10:02', odometer: 1500, fuel: 700);
        $this->seedRecord('10:03', odometer: 2000, fuel: 900);
        $this->em->flush();

        $report = $this->service->report('AB123', $this->at('00:00'), $this->at('23:59'));

        self::assertNotNull($report);
        self::assertEqualsWithDelta(1.0, $report->distanceKm, 1e-9);  // 1000 -> 2000 m
        self::assertEqualsWithDelta(0.4, $report->fuelLitres, 1e-9);  // 500 -> 900 mL
    }

    public function testReturnsNullForAnUnknownPlate(): void
    {
        self::assertNull($this->service->report('ZZ999', $this->at('00:00'), $this->at('23:59')));
    }

    public function testReturnsAZeroReportForAKnownPlateWithNoRecordsInRange(): void
    {
        $this->seedObservation('AB123', '10:00');
        $this->seedRecord('10:01', odometer: 1000);
        $this->em->flush();

        $report = $this->service->report('AB123', $this->at('12:00'), $this->at('13:00'));

        self::assertNotNull($report);
        self::assertEqualsWithDelta(0.0, $report->distanceKm, 1e-9);
    }

    public function testCountsOnlyRecordsWhileThePlateWasCurrent(): void
    {
        $this->seedObservation('AB123', '10:00');
        $this->seedObservation('XY999', '10:30'); // device reassigned
        $this->seedRecord('10:10', odometer: 1000); // AB123 window
        $this->seedRecord('10:20', odometer: 1600); // AB123 window
        $this->seedRecord('10:45', odometer: 5000); // XY999 window
        $this->em->flush();

        $report = $this->service->report('AB123', $this->at('00:00'), $this->at('23:59'));

        self::assertNotNull($report);
        // Only the two AB123-window records: 1600 - 1000 = 600 m. The jump to
        // 5000 in the next plate's window is not counted.
        self::assertEqualsWithDelta(0.6, $report->distanceKm, 1e-9);
    }

    private function seedObservation(string $plate, string $time): void
    {
        $this->em->persist(new DevicePlateObservation($this->device, $plate, $this->at($time)));
    }

    private function seedRecord(string $time, ?int $odometer = null, ?int $fuel = null): void
    {
        $this->em->persist(new TelematicsRecord(
            device: $this->device,
            recordedAt: $this->at($time),
            totalOdometerMeters: $odometer,
            engineTotalFuelUsedMilliliters: $fuel,
        ));
    }

    private function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable("2026-07-13T{$time}:00+00:00");
    }
}
