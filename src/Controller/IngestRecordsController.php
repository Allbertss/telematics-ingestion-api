<?php

declare(strict_types=1);

namespace App\Controller;

use App\Ingestion\RecordIngestionService;
use App\Ingestion\RejectedRecord;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IngestRecordsController
{
    public function __construct(
        private readonly RecordIngestionService $ingestion,
    ) {
    }

    #[Route('/api/v1/telematics/records', name: 'telematics_ingest', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = json_decode($request->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->error('Request body is not valid JSON.');
        }

        if (!is_array($payload)) {
            return $this->error('Request body must be a JSON object.');
        }

        $device = $payload['device'] ?? null;

        if (!is_string($device) || '' === trim($device)) {
            return $this->error('Missing or blank "device" identifier.');
        }

        $records = $payload['records'] ?? null;

        if (!is_array($records)) {
            return $this->error('Missing or invalid "records" list.');
        }

        $summary = $this->ingestion->ingest(trim($device), array_values($records));

        return new JsonResponse([
            'received' => $summary->received,
            'stored' => $summary->stored,
            'duplicates' => $summary->duplicates,
            'rejected' => array_map(
                static fn (RejectedRecord $rejected): array => [
                    'index' => $rejected->index,
                    'reason' => $rejected->reason,
                ],
                $summary->rejected,
            ),
        ]);
    }

    private function error(string $message): JsonResponse
    {
        return new JsonResponse(['error' => $message], Response::HTTP_BAD_REQUEST);
    }
}
