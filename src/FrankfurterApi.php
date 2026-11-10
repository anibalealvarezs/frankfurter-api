<?php

declare(strict_types=1);

namespace Anibalealvarezs\FrankfurterApi;

use Anibalealvarezs\ApiSkeleton\Clients\NoAuthClient;
use DateTimeInterface;
use Exception;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class FrankfurterApi extends NoAuthClient
{
    public const DEFAULT_BASE_URL = 'https://api.frankfurter.dev/v1/';

    /**
     * @param string $baseUrl
     * @param GuzzleClient|null $guzzleClient
     * @param LoggerInterface|null $logger
     * @throws Exception
     */
    public function __construct(
        string $baseUrl = self::DEFAULT_BASE_URL,
        ?GuzzleClient $guzzleClient = null,
        ?LoggerInterface $logger = null
    ) {
        // Ensure trailing slash
        $normalizedBaseUrl = rtrim($baseUrl, '/') . '/';

        parent::__construct(
            baseUrl: $normalizedBaseUrl,
            defaultHeaders: [
                'Accept' => 'application/json',
                'User-Agent' => 'anibalealvarezs-frankfurter-api/1.0',
            ],
            guzzleClient: $guzzleClient,
            logger: $logger,
        );

        $this->setResponseErrorDetector('message');
        $this->setErrorMessageParser(static fn ($data) => $data['message'] ?? (is_string($data) ? $data : json_encode($data)));
    }

    /**
     * Get the latest foreign exchange rates.
     *
     * @param string $base
     * @param array<string> $symbols
     * @param float|null $amount
     * @return array<string, mixed>
     * @throws GuzzleException
     */
    public function getLatest(string $base = 'USD', array $symbols = [], ?float $amount = null): array
    {
        return $this->requestRates('latest', $base, $symbols, $amount);
    }

    /**
     * Get historical exchange rates for a specific date.
     *
     * @param string|DateTimeInterface $date (Format: YYYY-MM-DD)
     * @param string $base
     * @param array<string> $symbols
     * @param float|null $amount
     * @return array<string, mixed>
     * @throws GuzzleException
     */
    public function getHistorical(string|DateTimeInterface $date, string $base = 'USD', array $symbols = [], ?float $amount = null): array
    {
        $dateStr = $this->formatDate($date);
        return $this->requestRates($dateStr, $base, $symbols, $amount);
    }

    /**
     * Get historical exchange rates for a date range in a single batch request.
     *
     * @param string|DateTimeInterface $startDate (Format: YYYY-MM-DD)
     * @param string|DateTimeInterface $endDate (Format: YYYY-MM-DD)
     * @param string $base
     * @param array<string> $symbols
     * @return array<string, mixed>
     * @throws GuzzleException
     */
    public function getHistoricalRange(
        string|DateTimeInterface $startDate,
        string|DateTimeInterface $endDate,
        string $base = 'USD',
        array $symbols = []
    ): array {
        $startStr = $this->formatDate($startDate);
        $endStr = $this->formatDate($endDate);

        if ($startStr > $endStr) {
            throw new InvalidArgumentException("Start date ({$startStr}) cannot be greater than end date ({$endStr}).");
        }

        $endpoint = "{$startStr}..{$endStr}";
        return $this->requestRates($endpoint, $base, $symbols);
    }

    /**
     * Get all currencies supported by the European Central Bank via Frankfurter.
     *
     * @return array<string, string> Key: currency code (e.g. 'EUR'), Value: Currency name (e.g. 'Euro')
     * @throws GuzzleException
     */
    public function getCurrencies(): array
    {
        $response = $this->performRequest(
            method: 'GET',
            endpoint: 'currencies'
        );

        $contents = $response->getBody()->getContents();
        return json_decode($contents, true) ?? [];
    }

    /**
     * Internal helper to perform rate requests and parse JSON.
     *
     * @param string $endpoint
     * @param string $base
     * @param array<string> $symbols
     * @param float|null $amount
     * @return array<string, mixed>
     * @throws GuzzleException
     */
    protected function requestRates(string $endpoint, string $base = 'USD', array $symbols = [], ?float $amount = null): array
    {
        $query = [
            'base' => strtoupper(trim($base)),
        ];

        if (!empty($symbols)) {
            $normalizedSymbols = array_map(static fn ($s) => strtoupper(trim($s)), $symbols);
            $query['symbols'] = implode(',', $normalizedSymbols);
        }

        if ($amount !== null && $amount > 0) {
            $query['amount'] = $amount;
        }

        $response = $this->performRequest(
            method: 'GET',
            endpoint: $endpoint,
            query: $query
        );

        $contents = $response->getBody()->getContents();
        return json_decode($contents, true) ?? [];
    }

    /**
     * Format DateTimeInterface or date string into YYYY-MM-DD.
     *
     * @param string|DateTimeInterface $date
     * @return string
     */
    protected function formatDate(string|DateTimeInterface $date): string
    {
        if ($date instanceof DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        $trimmed = trim($date);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed)) {
            throw new InvalidArgumentException("Invalid date format: '{$date}'. Expected format YYYY-MM-DD.");
        }

        return $trimmed;
    }
}
