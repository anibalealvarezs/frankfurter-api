<?php

declare(strict_types=1);

namespace Tests;

use Anibalealvarezs\FrankfurterApi\FrankfurterApi;
use DateTimeImmutable;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FrankfurterApiTest extends TestCase
{
    protected function createMockApi(array $responses): FrankfurterApi
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $guzzleClient = new GuzzleClient(['handler' => $handlerStack]);

        return new FrankfurterApi(
            baseUrl: 'https://api.frankfurter.dev/v1/',
            guzzleClient: $guzzleClient
        );
    }

    public function testGetLatest(): void
    {
        $payload = [
            'amount' => 1.0,
            'base' => 'USD',
            'date' => '2026-03-01',
            'rates' => [
                'EUR' => 0.92,
                'GBP' => 0.78,
            ],
        ];

        $api = $this->createMockApi([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload)),
        ]);

        $result = $api->getLatest(base: 'USD', symbols: ['EUR', 'GBP']);

        $this->assertSame('USD', $result['base']);
        $this->assertEquals(0.92, $result['rates']['EUR']);
        $this->assertEquals(0.78, $result['rates']['GBP']);
    }

    public function testGetHistoricalSingleDate(): void
    {
        $payload = [
            'amount' => 1.0,
            'base' => 'EUR',
            'date' => '2025-01-15',
            'rates' => [
                'USD' => 1.085,
            ],
        ];

        $api = $this->createMockApi([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload)),
        ]);

        $date = new DateTimeImmutable('2025-01-15');
        $result = $api->getHistorical($date, base: 'EUR', symbols: ['USD']);

        $this->assertSame('2025-01-15', $result['date']);
        $this->assertSame('EUR', $result['base']);
        $this->assertEquals(1.085, $result['rates']['USD']);
    }

    public function testGetHistoricalRange(): void
    {
        $payload = [
            'amount' => 1.0,
            'base' => 'USD',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-03',
            'rates' => [
                '2025-01-01' => ['EUR' => 0.920],
                '2025-01-02' => ['EUR' => 0.921],
                '2025-01-03' => ['EUR' => 0.922],
            ],
        ];

        $api = $this->createMockApi([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload)),
        ]);

        $result = $api->getHistoricalRange('2025-01-01', '2025-01-03', 'USD', ['EUR']);

        $this->assertSame('USD', $result['base']);
        $this->assertArrayHasKey('2025-01-01', $result['rates']);
        $this->assertEquals(0.922, $result['rates']['2025-01-03']['EUR']);
    }

    public function testHistoricalRangeRejectsInvertedDates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $api = $this->createMockApi([]);
        $api->getHistoricalRange('2025-01-10', '2025-01-01');
    }

    public function testGetCurrencies(): void
    {
        $payload = [
            'USD' => 'United States Dollar',
            'EUR' => 'Euro',
            'GBP' => 'British Pound',
        ];

        $api = $this->createMockApi([
            new Response(200, ['Content-Type' => 'application/json'], json_encode($payload)),
        ]);

        $currencies = $api->getCurrencies();

        $this->assertArrayHasKey('USD', $currencies);
        $this->assertArrayHasKey('EUR', $currencies);
        $this->assertSame('Euro', $currencies['EUR']);
    }
}
