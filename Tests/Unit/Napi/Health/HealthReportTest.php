<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Tests\Unit\Napi\Health;

use Nordkirche\NkcBase\Napi\Health\HealthReport;
use Nordkirche\NkcBase\Napi\Health\HealthStatus;
use Nordkirche\NkcBase\Napi\Health\ProbeResult;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class HealthReportTest extends UnitTestCase
{
    #[Test]
    public function theWorstStatusWins(): void
    {
        self::assertSame(HealthStatus::Ok, HealthStatus::worst());
        self::assertSame(HealthStatus::Slow, HealthStatus::worst(HealthStatus::Ok, HealthStatus::Slow));
        self::assertSame(HealthStatus::Config, HealthStatus::worst(HealthStatus::Slow, HealthStatus::Config));
        self::assertSame(HealthStatus::Auth, HealthStatus::worst(HealthStatus::Config, HealthStatus::Auth, HealthStatus::Ok));
        self::assertSame(HealthStatus::Down, HealthStatus::worst(HealthStatus::Auth, HealthStatus::Down, HealthStatus::Slow));
    }

    #[Test]
    public function statusesMapToBackendSeverities(): void
    {
        self::assertSame('success', HealthStatus::Ok->state());
        self::assertSame('warning', HealthStatus::Slow->state());
        self::assertSame('warning', HealthStatus::Config->state());
        self::assertSame('danger', HealthStatus::Auth->state());
        self::assertSame('danger', HealthStatus::Down->state());
        self::assertFalse(HealthStatus::Ok->isProblem());
        self::assertTrue(HealthStatus::Slow->isProblem());
    }

    #[Test]
    public function reportOfResultsHasStatusAndTimes(): void
    {
        $report = HealthReport::fromResults([
            $this->createResult('institutions', HealthStatus::Ok, 100.0),
            $this->createResult('people', HealthStatus::Slow, 2000.0),
            $this->createResult('events', HealthStatus::Ok, 300.0),
        ], 'https://example.org/api/v1/');

        self::assertSame(HealthStatus::Slow, $report->status);
        self::assertSame(800.0, $report->getAverageMs());
        self::assertSame(2000.0, $report->getMaxMs());
        self::assertEqualsWithDelta(time(), $report->checkedAt, 5);
    }

    #[Test]
    public function emptyReportHasZeroTimes(): void
    {
        $report = HealthReport::misconfigured(['NDK_NAPI_HOST']);

        self::assertSame(HealthStatus::Config, $report->status);
        self::assertSame(0.0, $report->getAverageMs());
        self::assertSame(0.0, $report->getMaxMs());
        self::assertSame(['NDK_NAPI_HOST'], $report->missingSettings);
    }

    #[Test]
    public function reportSurvivesStorageAsJson(): void
    {
        $report = HealthReport::fromResults([
            $this->createResult('institutions', HealthStatus::Ok, 120.04),
            $this->createResult('people', HealthStatus::Auth, 80.0, 'Zugang abgelehnt (HTTP 401)'),
        ], 'https://example.org/api/v1/');

        $stored = json_decode((string)json_encode($report), true);
        $restored = HealthReport::fromArray($stored);

        self::assertSame(HealthStatus::Auth, $restored->status);
        self::assertSame($report->checkedAt, $restored->checkedAt);
        self::assertSame('https://example.org/api/v1/', $restored->baseUri);
        self::assertCount(2, $restored->results);
        self::assertSame('people', $restored->results[1]->endpoint);
        self::assertSame(HealthStatus::Auth, $restored->results[1]->status);
        self::assertSame('Zugang abgelehnt (HTTP 401)', $restored->results[1]->error);
        self::assertSame(120.0, $restored->results[0]->totalMs, 'Times are rounded to 0.1 ms');
        self::assertSame(['dns', 'connect', 'tls', 'wait', 'transfer'], array_keys($restored->results[0]->phases));
        self::assertSame('danger', $stored['state']);
    }

    #[Test]
    public function brokenStoredDataDoesNotFail(): void
    {
        $report = HealthReport::fromArray(['status' => 'unknown', 'results' => [['status' => 'nonsense']]]);

        self::assertSame(HealthStatus::Down, $report->status);
        self::assertSame(HealthStatus::Down, $report->results[0]->status);
        self::assertSame(0, $report->checkedAt);
        self::assertSame(0.0, $report->results[0]->phases['dns']);
    }

    private function createResult(string $endpoint, HealthStatus $status, float $totalMs, string $error = ''): ProbeResult
    {
        return new ProbeResult(
            $endpoint,
            $status,
            $status === HealthStatus::Auth ? 401 : 200,
            $totalMs,
            ['dns' => 1.0, 'connect' => 2.0, 'tls' => 3.0, 'wait' => $totalMs - 10, 'transfer' => 4.0],
            null,
            10,
            512,
            '192.0.2.1',
            $error
        );
    }
}
