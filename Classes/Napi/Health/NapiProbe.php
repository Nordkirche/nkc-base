<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Napi\Health;

use GuzzleHttp\TransferStats;
use Nordkirche\NkcBase\Service\NapiSettings;
use TYPO3\CMS\Core\Http\Client\GuzzleClientFactory;

/**
 * Checks availability and response times of the NAPI.
 *
 * Uses its own HTTP client, so the requests bypass the HTTP cache of the NDK.
 */
class NapiProbe
{
    public function __construct(
        private readonly NapiSettings $settings,
        private readonly GuzzleClientFactory $clientFactory,
    ) {}

    /**
     * @param string[]|null $endpoints Defaults to the endpoints of the extension configuration
     */
    public function run(?array $endpoints = null): HealthReport
    {
        $missing = $this->settings->getMissing();
        if ($missing !== []) {
            return HealthReport::misconfigured($missing);
        }

        $results = [];
        foreach ($endpoints ?? $this->settings->getHealthEndpoints() as $endpoint) {
            $results[] = $this->probe($endpoint);
        }
        return HealthReport::fromResults($results, $this->settings->getBaseUri());
    }

    public function probe(string $endpoint): ProbeResult
    {
        $stats = [];
        $start = hrtime(true);
        $options = [
            'headers' => [
                'Accept' => 'application/vnd.api+json',
                'uid' => $this->settings->get('NDK_NAPI_USER_ID'),
                'access-token' => $this->settings->get('NDK_NAPI_ACCESS_TOKEN'),
                'Cache-Control' => 'no-cache',
            ],
            'query' => ['page' => ['size' => 1]],
            'timeout' => $this->settings->getTimeout(),
            'connect_timeout' => min(10, $this->settings->getTimeout()),
            'http_errors' => false,
            'allow_redirects' => false,
            'on_stats' => static function (TransferStats $transferStats) use (&$stats): void {
                $stats = $transferStats->getHandlerStats();
            },
        ];
        if ($this->settings->getHttpAuth() !== []) {
            $options['auth'] = $this->settings->getHttpAuth();
        }

        try {
            $response = $this->clientFactory->getClient()->request('GET', $this->settings->getBaseUri() . $endpoint, $options);
        } catch (\Throwable $e) {
            return $this->createResult($endpoint, HealthStatus::Down, 0, $stats, $start, null, null, $e->getMessage());
        }

        $httpStatus = $response->getStatusCode();
        $body = json_decode((string)$response->getBody(), true);
        $runtime = $response->getHeaderLine('X-Runtime');
        $serverMs = is_numeric($runtime) ? (float)$runtime * 1000 : null;
        $recordCount = is_array($body) && isset($body['meta']['record_count']) ? (int)$body['meta']['record_count'] : null;

        [$status, $error] = match (true) {
            $httpStatus === 401, $httpStatus === 403 => [HealthStatus::Auth, 'Zugang abgelehnt (HTTP ' . $httpStatus . ')'],
            // Redirects, e.g. to a login page, are failures, too
            $httpStatus >= 300 || $httpStatus < 200 => [HealthStatus::Down, 'HTTP ' . $httpStatus],
            !is_array($body) || !isset($body['data']) => [HealthStatus::Down, 'Ungültige Antwort (kein JSON:API)'],
            default => [HealthStatus::Ok, ''],
        };

        $result = $this->createResult($endpoint, $status, $httpStatus, $stats, $start, $serverMs, $recordCount, $error);
        if ($status === HealthStatus::Ok && $result->totalMs > $this->settings->getSlowThreshold()) {
            return new ProbeResult(
                $result->endpoint,
                HealthStatus::Slow,
                $result->httpStatus,
                $result->totalMs,
                $result->phases,
                $result->serverMs,
                $result->recordCount,
                $result->bytes,
                $result->ip,
                sprintf('Langsamer als %d ms', $this->settings->getSlowThreshold())
            );
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $stats cURL timings (seconds)
     */
    private function createResult(string $endpoint, HealthStatus $status, int $httpStatus, array $stats, int $start, ?float $serverMs, ?int $recordCount, string $error): ProbeResult
    {
        $total = isset($stats['total_time']) ? (float)$stats['total_time'] * 1000 : (hrtime(true) - $start) / 1e6;
        $dns = (float)($stats['namelookup_time'] ?? 0) * 1000;
        $connect = (float)($stats['connect_time'] ?? 0) * 1000;
        $tlsDone = isset($stats['appconnect_time']) && $stats['appconnect_time'] > 0
            ? (float)$stats['appconnect_time'] * 1000
            : (float)($stats['pretransfer_time'] ?? 0) * 1000;
        $firstByte = (float)($stats['starttransfer_time'] ?? 0) * 1000;

        $phases = [
            'dns' => $dns,
            'connect' => max(0.0, $connect - $dns),
            'tls' => max(0.0, $tlsDone - $connect),
            'wait' => $firstByte > 0 ? max(0.0, $firstByte - max($tlsDone, $connect)) : 0.0,
            'transfer' => $firstByte > 0 ? max(0.0, $total - $firstByte) : 0.0,
        ];

        return new ProbeResult(
            $endpoint,
            $status,
            $httpStatus,
            $total,
            $phases,
            $serverMs,
            $recordCount,
            (int)($stats['size_download'] ?? 0),
            (string)($stats['primary_ip'] ?? ''),
            $error
        );
    }
}
