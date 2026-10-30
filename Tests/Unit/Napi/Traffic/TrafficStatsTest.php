<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Tests\Unit\Napi\Traffic;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Nordkirche\NkcBase\Napi\Traffic\TrafficStats;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Registry;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class TrafficStatsTest extends UnitTestCase
{
    /**
     * Content of the registry, namespace => key => value
     */
    private array $registryEntries = [];

    public static function endpointDataProvider(): \Generator
    {
        yield 'collection' => ['/api/v1/institutions', '/api/v1/', 'institutions'];
        yield 'single record' => ['/api/v1/people/123', '/api/v1/', 'people/{id}'];
        yield 'id list' => ['/api/v1/events/1,2,3', '/api/v1/', 'events/{id}'];
        yield 'nested' => ['/api/v1/institutions/12/people', '/api/v1/', 'institutions/{id}/people'];
        yield 'other base path' => ['/v2/events/5', '', 'v2/events/{id}'];
        yield 'root' => ['/api/v1/', '/api/v1/', '/'];
    }

    #[Test]
    #[DataProvider('endpointDataProvider')]
    public function endpointsAreNormalized(string $path, string $basePath, string $expected): void
    {
        self::assertSame($expected, TrafficStats::normalizeEndpoint($path, $basePath));
    }

    #[Test]
    public function summaryAddsUpTheRequests(): void
    {
        $stats = $this->createStats();
        $stats->record('people/{id}', 100.0, false, false);
        $stats->record('people/{id}', 300.0, true, false);
        $stats->record('institutions', 50.0, false, true);
        $stats->record('institutions', 50.0, false, true);

        $summary = $stats->getSummary(1);

        self::assertSame(4, $summary['count']);
        self::assertSame(1, $summary['errors']);
        self::assertSame(2, $summary['cacheHits']);
        self::assertSame(125.0, $summary['avgMs']);
        self::assertSame(300.0, $summary['maxMs']);
        self::assertSame(0.25, $summary['errorRate']);
        self::assertSame(0.5, $summary['cacheRate']);
        self::assertSame(['people/{id}', 'institutions'], array_keys($summary['endpoints']), 'Slowest endpoint first');
        self::assertSame(200.0, $summary['endpoints']['people/{id}']['avgMs']);
        self::assertSame(2, $summary['endpoints']['institutions']['count']);
    }

    #[Test]
    public function persistMergesWithStoredValuesOfOtherRequests(): void
    {
        $first = $this->createStats();
        $first->record('events', 100.0, false, false);
        $first->persist();

        $second = $this->createStats();
        $second->record('events', 300.0, false, false);
        $second->persist();

        $summary = $this->createStats()->getSummary(2);
        self::assertSame(2, $summary['count']);
        self::assertSame(200.0, $summary['endpoints']['events']['avgMs']);
        self::assertSame(300.0, $summary['endpoints']['events']['maxMs']);
    }

    #[Test]
    public function emptySummaryHasNoDivisionByZero(): void
    {
        $summary = $this->createStats()->getSummary(24);

        self::assertSame(0, $summary['count']);
        self::assertSame(0.0, $summary['avgMs']);
        self::assertSame(0.0, $summary['errorRate']);
        self::assertSame([], $summary['endpoints']);
    }

    #[Test]
    public function statisticsAreKeptInTheRegistryForTwoDays(): void
    {
        $this->registryEntries['nkc_base.napi']['traffic'] = [
            date('YmdH', time() - 3 * 86400) => ['count' => 5, 'errors' => 0, 'cacheHits' => 0, 'sumMs' => 50.0, 'maxMs' => 10.0, 'endpoints' => []],
            date('YmdH', time() - 2 * 3600) => ['count' => 2, 'errors' => 0, 'cacheHits' => 0, 'sumMs' => 20.0, 'maxMs' => 10.0, 'endpoints' => []],
        ];
        $stats = $this->createStats();
        $stats->record('events', 10.0, false, false);
        $stats->persist();

        self::assertCount(2, $this->registryEntries['nkc_base.napi']['traffic'], 'Hours older than 48 hours are dropped');
        self::assertSame(3, $this->createStats()->getSummary(24)['count']);
        self::assertSame(1, $this->createStats()->getSummary(1)['count']);
    }

    #[Test]
    public function aBrokenRegistryNeverBreaksTheRequest(): void
    {
        $registry = self::createStub(Registry::class);
        $registry->method('get')->willReturn(null);
        $registry->method('set')->willThrowException(new \RuntimeException('table sys_registry missing'));
        $stats = new TrafficStats($registry);
        $stats->record('events', 10.0, false, false);

        $stats->persist();

        self::assertSame(0, $stats->getSummary(1)['count'], 'Pending values are discarded after persist');
    }

    #[Test]
    public function middlewareRecordsResponsesErrorsAndCacheHits(): void
    {
        $stats = $this->createStats();
        $stack = HandlerStack::create(new MockHandler([
            new Response(200),
            new Response(200, ['X-Kevinrob-Cache' => 'HIT']),
            new Response(500),
            new ConnectException('timeout', new Request('GET', 'events')),
        ]));
        $stack->push($stats->createMiddleware('https://www.nordkirche.de/api/v1/'));
        $client = new Client(['handler' => $stack, 'http_errors' => false, 'base_uri' => 'https://www.nordkirche.de/api/v1/']);

        $client->get('people/17');
        $client->get('people/18');
        $client->get('events');
        try {
            $client->get('events');
            self::fail('Connection errors are passed on');
        } catch (ConnectException) {
        }

        $summary = $stats->getSummary(1);
        self::assertSame(4, $summary['count']);
        self::assertSame(2, $summary['errors']);
        self::assertSame(1, $summary['cacheHits']);
        self::assertSame(2, $summary['endpoints']['people/{id}']['count']);
        self::assertSame(2, $summary['endpoints']['events']['errors']);
    }

    private function createStats(): TrafficStats
    {
        $registry = self::createStub(Registry::class);
        $registry->method('get')->willReturnCallback(fn(string $namespace, string $key, mixed $default = null): mixed => $this->registryEntries[$namespace][$key] ?? $default);
        $registry->method('set')->willReturnCallback(function (string $namespace, string $key, mixed $value): void {
            $this->registryEntries[$namespace][$key] = $value;
        });
        return new TrafficStats($registry);
    }
}
