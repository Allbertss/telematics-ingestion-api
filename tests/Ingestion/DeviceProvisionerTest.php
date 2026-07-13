<?php

declare(strict_types=1);

namespace App\Tests\Ingestion;

use App\Entity\Device;
use App\Ingestion\DeviceProvisioner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DeviceProvisionerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DeviceProvisioner $provisioner;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->provisioner = new DeviceProvisioner($this->em);
    }

    public function testProvisionsANewDeviceOnFirstSighting(): void
    {
        $device = $this->provisioner->provision(
            '356938035643809',
            new \DateTimeImmutable('2026-07-13T10:00:00+00:00'),
        );
        $this->em->flush();

        self::assertNotNull($device->getId());
        self::assertSame('356938035643809', $device->getIdentifier());
    }

    public function testSecondSightingReusesTheDeviceWithoutDuplicatingOrOverwriting(): void
    {
        $first = $this->provisioner->provision(
            '356938035643809',
            new \DateTimeImmutable('2026-07-13T10:00:00+00:00'),
        );
        $this->em->flush();

        $again = $this->provisioner->provision(
            '356938035643809',
            new \DateTimeImmutable('2026-07-13T12:00:00+00:00'), // later; must NOT overwrite first_seen_at
        );
        $this->em->flush();

        self::assertSame($first->getId(), $again->getId());

        $this->em->clear();
        $count = $this->em->getRepository(Device::class)->count(['identifier' => '356938035643809']);
        self::assertSame(1, $count);

        $reloaded = $this->em->getRepository(Device::class)->findOneBy(['identifier' => '356938035643809']);
        self::assertInstanceOf(Device::class, $reloaded);
        self::assertSame(
            '2026-07-13 10:00:00',
            $reloaded->getFirstSeenAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        );
    }
}
