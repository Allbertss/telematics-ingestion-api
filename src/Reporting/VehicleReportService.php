<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Domain\Report\DistanceFuelCalculator;
use App\Domain\Report\DistanceFuelReport;

final class VehicleReportService
{
    public function __construct(
        private readonly ReportingRepository $repository,
        private readonly DistanceFuelCalculator $calculator,
    ) {
    }

    public function report(string $registrationNumber, \DateTimeImmutable $from, \DateTimeImmutable $to): ?DistanceFuelReport
    {
        $plate = strtoupper(trim($registrationNumber));

        if (!$this->repository->plateExists($plate)) {
            return null;
        }

        return $this->calculator->calculate($this->repository->segments($plate, $from, $to));
    }
}
