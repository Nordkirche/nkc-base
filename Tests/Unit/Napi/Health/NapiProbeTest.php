<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Tests\Unit\Napi\Health;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Nordkirche\NkcBase\Napi\Health\HealthStatus;
use Nordkirche\NkcBase\Napi\Health\NapiProbe;
use Nordkirche\NkcBase\Tests\Unit\Fixtures\NapiSettingsFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\RequestInterface;
use TYPO3\CMS\Core\Http\Client\GuzzleClientFactory;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class NapiProbeTest extends UnitTestCase
{
    use NapiSettingsFixture;

    /**
     * @var array<int, array{request: RequestInterface, options: array}>
     */
    private array $history = [];

    #[Test]
    public function aValidResponseIsOk(): void
    {
        $probe = $this->createProbe([$this->jsonApiResponse(['X-Runtime' => '0.125'])]);

        $result = $probe->probe('institutions');

        self::assertSame(HealthStatus::Ok, $result->status);
        self::assertSame(200, $result->httpStatus);
        self::assertSame(1234, $result->recordCount);
        self::assertSame(125.0, $result->serverMs);
        self::assertSame('', $result->error);
        self::assertGreaterThan(0, $result->totalMs);
    }

    #[Test]
    public function theRequestBypassesCachesAndAsksForOneRecord(): void
    {
        $this->createProbe([$this->jsonApiResponse()], ['NDK_HTTP_AUTH_USERNAME' => 'user', 'NDK_HTTP_AUTH_PASSWORD' => 'secret'])
            ->probe('events');

        $request = $this->history[0]['request'];
        self::assertSame('https://www.nordkirche.de/api/v1/events?page%5Bsize%5D=1', (string)$request->getUri());
        self::assertSame('42', $request->getHeaderLine('uid'));
        self::assertSame('test-token', $request->getHeaderLine('access-token'));
        self::assertSame('no-cache', $request->getHeaderLine('Cache-Control'));
        self::assertSame('Basic ' . base64_encode('user:secret'), $request->getHeaderLine('Authorization'));
        self::assertFalse($this->history[0]['options']['allow_redirects']);
    }

    public static function failureDataProvider(): \Generator
    {
        yield 'unauthorized' => [new Response(401), HealthStatus::Auth, 'Zugang abgelehnt (HTTP 401)'];
        yield 'forbidden' => [new Response(403), HealthStatus::Auth, 'Zugang abgelehnt (HTTP 403)'];
        yield 'server error' => [new Response(503), HealthStatus::Down, 'HTTP 503'];
        yield 'not found' => [new Response(404), HealthStatus::Down, 'HTTP 404'];
        yield 'redirect' => [new Response(302, ['Location' => '/login']), HealthStatus::Down, 'HTTP 302'];
        yield 'no JSON' => [new Response(200, [], '<html>Wartungsarbeiten</html>'), HealthStatus::Down, 'Ungültige Antwort (kein JSON:API)'];
        yield 'JSON without data' => [new Response(200, [], '{"errors": []}'), HealthStatus::Down, 'Ungültige Antwort (kein JSON:API)'];
    }

    #[Test]
    #[DataProvider('failureDataProvider')]
    public function failuresAreClassified(Response $response, HealthStatus $expectedStatus, string $expectedError): void
    {
        $result = $this->createProbe([$response])->probe('people');

        self::assertSame($expectedStatus, $result->status);
        self::assertSame($expectedError, $result->error);
        self::assertSame($response->getStatusCode(), $result->httpStatus);
    }

    #[Test]
    public function connectionErrorsMeanDown(): void
    {
        $result = $this->createProbe([new ConnectException('Could not resolve host', new Request('GET', 'events'))])
            ->probe('events');

        self::assertSame(HealthStatus::Down, $result->status);
        self::assertSame(0, $result->httpStatus);
        self::assertStringContainsString('Could not resolve host', $result->error);
    }

    #[Test]
    public function slowResponsesAreReported(): void
    {
        $handler = static function (): \GuzzleHttp\Promise\PromiseInterface {
            usleep(20000);
            return Create::promiseFor(new Response(200, [], '{"data": []}'));
        };
        $probe = new NapiProbe(
            $this->createNapiSettings(self::NAPI_CONFIGURATION + ['NDK_HEALTH_SLOW_MS' => '5']),
            $this->createClientFactory($handler)
        );

        $result = $probe->probe('categories');

        self::assertSame(HealthStatus::Slow, $result->status);
        self::assertSame('Langsamer als 5 ms', $result->error);
    }

    #[Test]
    public function runChecksAllEndpointsAndReportsTheWorstStatus(): void
    {
        $probe = $this->createProbe([$this->jsonApiResponse(), new Response(500), $this->jsonApiResponse()]);

        $report = $probe->run(['institutions', 'people', 'events']);

        self::assertSame(HealthStatus::Down, $report->status);
        self::assertCount(3, $report->results);
        self::assertSame(['institutions', 'people', 'events'], array_map(static fn($result) => $result->endpoint, $report->results));
        self::assertSame('https://www.nordkirche.de:443/api/v1/', $report->baseUri);
    }

    #[Test]
    public function runWithoutConfigurationSendsNoRequest(): void
    {
        $configuration = self::NAPI_CONFIGURATION;
        unset($configuration['NDK_NAPI_ACCESS_TOKEN']);
        $probe = $this->createProbe([], [], $configuration);

        $report = $probe->run();

        self::assertSame(HealthStatus::Config, $report->status);
        self::assertSame(['NDK_NAPI_ACCESS_TOKEN'], $report->missingSettings);
        self::assertSame([], $this->history);
    }

    /**
     * @param array<int, Response|\Throwable> $responses
     */
    private function createProbe(array $responses, array $additionalSettings = [], ?array $configuration = null): NapiProbe
    {
        return new NapiProbe(
            $this->createNapiSettings(($configuration ?? self::NAPI_CONFIGURATION) + $additionalSettings),
            $this->createClientFactory(new MockHandler($responses))
        );
    }

    private function createClientFactory(callable $handler): GuzzleClientFactory
    {
        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::history($this->history));
        $factory = self::createStub(GuzzleClientFactory::class);
        $factory->method('getClient')->willReturn(new Client(['handler' => $stack]));
        return $factory;
    }

    private function jsonApiResponse(array $headers = []): Response
    {
        return new Response(200, ['Content-Type' => 'application/vnd.api+json'] + $headers, '{"data": [{"id": "1"}], "meta": {"record_count": 1234}}');
    }
}
