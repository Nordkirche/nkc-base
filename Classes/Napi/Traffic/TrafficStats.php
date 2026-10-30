<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Napi\Traffic;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\SingletonInterface;

/**
 * Response times of the real NAPI requests (frontend, backend, CLI).
 *
 * The NDK reports every request via a Guzzle middleware. The values are
 * collected in memory and written once per PHP request into hourly buckets
 * in the registry (sys_registry), so flushing the caches doesn't reset them.
 * Parallel requests may overwrite each other's counts, which is acceptable
 * for a status display.
 */
class TrafficStats implements SingletonInterface
{
    private const NAMESPACE = 'nkc_base.napi';
    private const KEY = 'traffic';

    /**
     * Hours that are kept
     */
    private const KEEP_HOURS = 48;

    /**
     * @var array<string, array<string, mixed>> Pending buckets of this PHP request, keyed by hour
     */
    private array $pending = [];

    private bool $shutdownRegistered = false;

    public function __construct(private readonly Registry $registry) {}

    /**
     * Guzzle middleware for the NDK client
     */
    public function createMiddleware(string $baseUri): callable
    {
        $basePath = (string)parse_url($baseUri, PHP_URL_PATH);

        return function (callable $handler) use ($basePath): callable {
            return function (RequestInterface $request, array $options) use ($handler, $basePath) {
                $start = hrtime(true);
                $endpoint = self::normalizeEndpoint($request->getUri()->getPath(), $basePath);

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) use ($start, $endpoint): ResponseInterface {
                        $this->record(
                            $endpoint,
                            (hrtime(true) - $start) / 1e6,
                            $response->getStatusCode() >= 400,
                            str_contains(strtoupper($response->getHeaderLine('X-Kevinrob-Cache')), 'HIT')
                        );
                        return $response;
                    },
                    function ($reason) use ($start, $endpoint) {
                        $this->record($endpoint, (hrtime(true) - $start) / 1e6, true, false);
                        throw $reason;
                    }
                );
            };
        };
    }

    public function record(string $endpoint, float $durationMs, bool $error, bool $cacheHit): void
    {
        $hour = date('YmdH');
        $bucket = $this->pending[$hour] ?? self::emptyBucket();
        $this->pending[$hour] = self::add($bucket, $endpoint, $durationMs, $error, $cacheHit);

        if (!$this->shutdownRegistered) {
            $this->shutdownRegistered = true;
            register_shutdown_function([$this, 'persist']);
        }
    }

    /**
     * Merges the values of this PHP request into the registry and drops old hours
     *
     * @internal Called at shutdown
     */
    public function persist(): void
    {
        if ($this->pending === []) {
            return;
        }
        try {
            $stored = $this->load();
            foreach ($this->pending as $hour => $pending) {
                $stored[$hour] = self::merge($stored[$hour] ?? self::emptyBucket(), $pending);
            }
            $oldest = date('YmdH', time() - self::KEEP_HOURS * 3600);
            $stored = array_filter($stored, static fn(string|int $hour): bool => (string)$hour > $oldest, ARRAY_FILTER_USE_KEY);
            $this->registry->set(self::NAMESPACE, self::KEY, $stored);
        } catch (\Throwable) {
            // Statistics must never break a request
        }
        $this->pending = [];
    }

    /**
     * Totals of the last hours, including the current one
     *
     * @return array{count: int, errors: int, cacheHits: int, sumMs: float, maxMs: float, avgMs: float, errorRate: float, cacheRate: float, endpoints: array<string, array<string, float|int>>}
     */
    public function getSummary(int $hours): array
    {
        $total = self::emptyBucket();
        try {
            $stored = $this->load();
        } catch (\Throwable) {
            $stored = [];
        }
        for ($i = 0; $i < $hours; $i++) {
            $hour = date('YmdH', time() - $i * 3600);
            if (isset($stored[$hour])) {
                $total = self::merge($total, $stored[$hour]);
            }
            if (isset($this->pending[$hour])) {
                $total = self::merge($total, $this->pending[$hour]);
            }
        }

        $count = (int)$total['count'];
        $total['avgMs'] = $count > 0 ? $total['sumMs'] / $count : 0.0;
        $total['errorRate'] = $count > 0 ? $total['errors'] / $count : 0.0;
        $total['cacheRate'] = $count > 0 ? $total['cacheHits'] / $count : 0.0;
        foreach ($total['endpoints'] as $name => $endpoint) {
            $total['endpoints'][$name]['avgMs'] = $endpoint['count'] > 0 ? $endpoint['sumMs'] / $endpoint['count'] : 0.0;
        }
        uasort($total['endpoints'], static fn(array $a, array $b): int => $b['avgMs'] <=> $a['avgMs']);

        return $total;
    }

    /**
     * Stored hours, keyed by "YmdH"
     *
     * @return array<string, array<string, mixed>>
     */
    private function load(): array
    {
        $stored = $this->registry->get(self::NAMESPACE, self::KEY, []);
        $hours = [];
        foreach (is_array($stored) ? $stored : [] as $hour => $bucket) {
            if (is_array($bucket) && isset($bucket['count'], $bucket['endpoints'])) {
                $hours[(string)$hour] = $bucket;
            }
        }
        return $hours;
    }

    /**
     * "/api/v1/people/123" => "people/{id}"
     */
    public static function normalizeEndpoint(string $path, string $basePath = ''): string
    {
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }
        $segments = array_map(
            static fn(string $segment): string => preg_match('/^\d+(,\d+)*$/', $segment) ? '{id}' : $segment,
            array_filter(explode('/', trim($path, '/')), static fn(string $segment): bool => $segment !== '')
        );
        return implode('/', $segments) ?: '/';
    }

    private static function emptyBucket(): array
    {
        return ['count' => 0, 'errors' => 0, 'cacheHits' => 0, 'sumMs' => 0.0, 'maxMs' => 0.0, 'endpoints' => []];
    }

    private static function add(array $bucket, string $endpoint, float $durationMs, bool $error, bool $cacheHit): array
    {
        $single = [
            'count' => 1,
            'errors' => (int)$error,
            'cacheHits' => (int)$cacheHit,
            'sumMs' => $durationMs,
            'maxMs' => $durationMs,
        ];
        return self::merge($bucket, $single + ['endpoints' => [$endpoint => $single]]);
    }

    private static function merge(array $a, array $b): array
    {
        $merged = [
            'count' => $a['count'] + $b['count'],
            'errors' => $a['errors'] + $b['errors'],
            'cacheHits' => $a['cacheHits'] + $b['cacheHits'],
            'sumMs' => $a['sumMs'] + $b['sumMs'],
            'maxMs' => max($a['maxMs'], $b['maxMs']),
            'endpoints' => $a['endpoints'],
        ];
        foreach ($b['endpoints'] as $name => $endpoint) {
            $merged['endpoints'][$name] = isset($merged['endpoints'][$name])
                ? array_diff_key(self::merge($merged['endpoints'][$name] + ['endpoints' => []], $endpoint + ['endpoints' => []]), ['endpoints' => true])
                : $endpoint;
        }
        return $merged;
    }
}
