<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Device;
use App\Entity\DevicePlateObservation;
use App\Entity\TelematicsRecord;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VehicleReportControllerTest extends WebTestCase
{
    private const string RANGE = 'from=2026-07-13T00:00:00Z&to=2026-07-13T23:59:00Z';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testReturnsAReportForAKnownVehicle(): void
    {
        $device = new Device('356938035643809', new \DateTimeImmutable('2026-07-13T09:00:00+00:00'));
        $this->em->persist($device);
        $this->em->persist(new DevicePlateObservation($device, 'AB123', $this->at('10:00')));
        $this->em->persist(new TelematicsRecord(device: $device, recordedAt: $this->at('10:01'), totalOdometerMeters: 1000, engineTotalFuelUsedMilliliters: 500));
        $this->em->persist(new TelematicsRecord(device: $device, recordedAt: $this->at('10:02'), totalOdometerMeters: 3000, engineTotalFuelUsedMilliliters: 1500));
        $this->em->flush();

        $this->client->request('GET', '/api/v1/vehicles/AB123/report?'.self::RANGE);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = $this->responseData();
        self::assertSame('AB123', $data['registrationNumber']);
        self::assertEqualsWithDelta(2.0, $this->asFloat($data['distanceKm']), 1e-9); // 1000 -> 3000 m
        self::assertEqualsWithDelta(1.0, $this->asFloat($data['fuelLitres']), 1e-9); // 500 -> 1500 mL
        self::assertEqualsWithDelta(50.0, $this->asFloat($data['fuelPer100Km']), 1e-9); // 1 L / 2 km * 100
    }

    private function asFloat(mixed $value): float
    {
        if (!is_int($value) && !is_float($value)) {
            self::fail('Expected a numeric value in the response.');
        }

        return (float) $value;
    }

    public function testReturns404ForAnUnknownVehicle(): void
    {
        $this->client->request('GET', '/api/v1/vehicles/UNKNOWN/report?'.self::RANGE);

        self::assertResponseStatusCodeSame(404);
    }

    public function testReturns400WhenFromOrToIsMissing(): void
    {
        $this->client->request('GET', '/api/v1/vehicles/AB123/report?from=2026-07-13T00:00:00Z');

        self::assertResponseStatusCodeSame(400);
    }

    public function testReturns400ForInvalidDates(): void
    {
        $this->client->request('GET', '/api/v1/vehicles/AB123/report?from=garbage&to=2026-07-13T23:59:00Z');

        self::assertResponseStatusCodeSame(400);
    }

    public function testReturns400WhenFromIsAfterTo(): void
    {
        $this->client->request('GET', '/api/v1/vehicles/AB123/report?from=2026-07-13T23:59:00Z&to=2026-07-13T00:00:00Z');

        self::assertResponseStatusCodeSame(400);
    }

    private function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable("2026-07-13T{$time}:00+00:00");
    }

    /**
     * @return array<array-key, mixed>
     */
    private function responseData(): array
    {
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($data);

        return $data;
    }
}
