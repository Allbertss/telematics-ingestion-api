<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class IngestRecordsControllerTest extends WebTestCase
{
    private const string URI = '/api/v1/telematics/records';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testAcceptsAValidBatchAndReturnsASummary(): void
    {
        $this->post([
            'device' => '356938035643809',
            'records' => [
                ['timestamp' => 1781849860.548, 'io' => ['216' => 1000]],
                ['timestamp' => 1781849861.548, 'io' => ['216' => 1100]],
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        $data = $this->responseData();
        self::assertSame(2, $data['received']);
        self::assertSame(2, $data['stored']);
        self::assertSame(0, $data['duplicates']);
        self::assertSame([], $data['rejected']);
    }

    public function testReportsPartialSuccessInTheSummary(): void
    {
        $this->post([
            'device' => '356938035643809',
            'records' => [
                ['timestamp' => 1781849860.548, 'io' => ['216' => 1000]],
                ['io' => ['216' => 1100]], // no timestamp -> rejected
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        $data = $this->responseData();
        self::assertSame(2, $data['received']);
        self::assertSame(1, $data['stored']);

        $rejected = $data['rejected'];
        self::assertIsArray($rejected);
        self::assertCount(1, $rejected);
    }

    public function testRejectsMalformedJsonWith400(): void
    {
        $this->client->request('POST', self::URI, server: ['CONTENT_TYPE' => 'application/json'], content: '{not json');

        self::assertResponseStatusCodeSame(400);
    }

    public function testRejectsAMissingDeviceIdentifierWith400(): void
    {
        $this->post(['records' => []]);

        self::assertResponseStatusCodeSame(400);
    }

    public function testRejectsAMissingRecordsListWith400(): void
    {
        $this->post(['device' => '356938035643809']);

        self::assertResponseStatusCodeSame(400);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(array $payload): void
    {
        $this->client->request(
            'POST',
            self::URI,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
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
