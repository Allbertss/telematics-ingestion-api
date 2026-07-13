<?php

declare(strict_types=1);

namespace App\Controller;

use App\Reporting\VehicleReportService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VehicleReportController
{
    public function __construct(
        private readonly VehicleReportService $reports,
    ) {
    }

    #[Route('/api/v1/vehicles/{registrationNumber}/report', name: 'vehicle_report', methods: ['GET'])]
    public function __invoke(string $registrationNumber, Request $request): JsonResponse
    {
        $fromRaw = $request->query->get('from');
        $toRaw = $request->query->get('to');

        if (!is_string($fromRaw) || '' === $fromRaw || !is_string($toRaw) || '' === $toRaw) {
            return $this->error('Query parameters "from" and "to" are required.');
        }

        try {
            $from = new \DateTimeImmutable($fromRaw);
            $to = new \DateTimeImmutable($toRaw);
        } catch (\Exception) {
            return $this->error('"from" and "to" must be valid ISO-8601 datetimes.');
        }

        if ($from > $to) {
            return $this->error('"from" must not be after "to".');
        }

        $report = $this->reports->report($registrationNumber, $from, $to);

        if (null === $report) {
            return new JsonResponse(
                ['error' => sprintf('No vehicle found for registration number "%s".', $registrationNumber)],
                Response::HTTP_NOT_FOUND,
            );
        }

        $fuelPer100Km = $report->fuelPer100Km();

        return new JsonResponse([
            'registrationNumber' => $registrationNumber,
            'from' => $from->format(\DateTimeInterface::ATOM),
            'to' => $to->format(\DateTimeInterface::ATOM),
            'distanceKm' => round($report->distanceKm, 3),
            'fuelLitres' => round($report->fuelLitres, 3),
            'fuelPer100Km' => null === $fuelPer100Km ? null : round($fuelPer100Km, 2),
        ]);
    }

    private function error(string $message): JsonResponse
    {
        return new JsonResponse(['error' => $message], Response::HTTP_BAD_REQUEST);
    }
}
