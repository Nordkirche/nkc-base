<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Tests\Unit\Middleware;

use Nordkirche\NkcBase\MapCache\MarkerInfoProviderInterface;
use Nordkirche\NkcBase\Middleware\MarkerInfoMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class MarkerInfoMiddlewareTest extends UnitTestCase
{
    public static function passThroughDataProvider(): \Generator
    {
        yield 'other path' => ['GET', 'https://example.org/marker-list?items=i:1', true];
        yield 'POST request' => ['POST', 'https://example.org/marker?items=i:1', true];
        yield 'no site' => ['GET', 'https://example.org/marker?items=i:1', false];
    }

    #[Test]
    #[DataProvider('passThroughDataProvider')]
    public function otherRequestsArePassedToTheNextHandler(string $method, string $uri, bool $withSite): void
    {
        $request = new ServerRequest($uri, $method);
        if ($withSite) {
            $request = $request->withAttribute('site', $this->createSite('https://example.org/'));
        }
        $provider = $this->createProvider('i');

        $response = $this->createMiddleware([$provider])->process($request, $this->createHandler());

        self::assertSame('next handler', (string)$response->getBody());
        self::assertSame([], $provider->calls);
    }

    #[Test]
    public function itemsAreGroupedByTypeAndPassedToTheProviders(): void
    {
        $institutions = $this->createProvider('i');
        $persons = $this->createProvider('p');
        $events = $this->createProvider('e');

        $response = $this->createMiddleware([$institutions, $persons, $events])
            ->process($this->createRequest('items=i:12,p:5,i:13,i:12'), $this->createHandler());

        self::assertSame([[12, 13]], $institutions->calls);
        self::assertSame([[5]], $persons->calls);
        self::assertSame([], $events->calls, 'Providers without items are not called');
        self::assertSame('<i>12,13</i><p>5</p>', (string)$response->getBody());
        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
        self::assertSame('noindex', $response->getHeaderLine('X-Robots-Tag'));
        self::assertStringContainsString('max-age', $response->getHeaderLine('Cache-Control'));
    }

    public static function invalidItemsDataProvider(): \Generator
    {
        yield 'no items' => [''];
        yield 'missing id' => ['items=i:'];
        yield 'not a number' => ['items=i:abc'];
        yield 'negative number' => ['items=i:-1'];
        yield 'long type' => ['items=inst:1'];
        yield 'upper case type' => ['items=I:1'];
        yield 'injection attempt' => ['items=i:1%3Cscript%3E'];
        yield 'array parameter' => ['items[]=i:1'];
    }

    #[Test]
    #[DataProvider('invalidItemsDataProvider')]
    public function invalidItemsAreIgnored(string $query): void
    {
        $provider = $this->createProvider('i');

        $response = $this->createMiddleware([$provider])->process($this->createRequest($query), $this->createHandler());

        self::assertSame([], $provider->calls);
        self::assertSame('', (string)$response->getBody());
    }

    #[Test]
    public function theNumberOfItemsIsLimited(): void
    {
        $provider = $this->createProvider('i');
        $items = implode(',', array_map(static fn(int $id): string => 'i:' . $id, range(1, 150)));

        $this->createMiddleware([$provider])->process($this->createRequest('items=' . $items), $this->createHandler());

        self::assertCount(100, $provider->calls[0]);
    }

    #[Test]
    public function aFailingProviderIsLoggedAndDoesNotStopTheOthers(): void
    {
        $failing = $this->createProvider('i', new \RuntimeException('NAPI down'));
        $persons = $this->createProvider('p');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(self::stringContains('NAPI down'));

        $response = (new MarkerInfoMiddleware([$failing, $persons], $logger))
            ->process($this->createRequest('items=i:1,p:2'), $this->createHandler());

        self::assertSame('<p>2</p>', (string)$response->getBody());
        self::assertStringNotContainsString('NAPI down', (string)$response->getBody());
    }

    public static function pathDataProvider(): \Generator
    {
        yield 'site in sub folder' => ['https://example.org/de/', 'https://example.org/de/marker'];
        yield 'root path of a site in a sub folder' => ['https://example.org/de/', 'https://example.org/marker'];
        yield 'site without trailing slash' => ['https://example.org/de', 'https://example.org/de/marker'];
    }

    #[Test]
    #[DataProvider('pathDataProvider')]
    public function theEndpointIsRelativeToTheSiteBase(string $siteBase, string $uri): void
    {
        $provider = $this->createProvider('i');
        $request = (new ServerRequest($uri . '?items=i:1', 'GET'))
            ->withQueryParams(['items' => 'i:1'])
            ->withAttribute('site', $this->createSite($siteBase));

        $this->createMiddleware([$provider])->process($request, $this->createHandler());

        self::assertSame([[1]], $provider->calls);
    }

    #[Test]
    public function theEndpointWorksWithLanguagePrefixes(): void
    {
        $provider = $this->createProvider('i');
        $request = (new ServerRequest('https://example.org/en/marker?items=i:1', 'GET'))
            ->withQueryParams(['items' => 'i:1'])
            ->withAttribute('site', $this->createSite('https://example.org/'))
            ->withAttribute('language', new SiteLanguage(1, 'en_US.UTF-8', new Uri('https://example.org/en/'), []));

        $this->createMiddleware([$provider])->process($request, $this->createHandler());

        self::assertSame([[1]], $provider->calls);
    }

    private function createMiddleware(array $providers): MarkerInfoMiddleware
    {
        return new MarkerInfoMiddleware($providers, new NullLogger());
    }

    private function createRequest(string $query): ServerRequestInterface
    {
        parse_str($query, $queryParams);
        return (new ServerRequest('https://example.org/marker?' . $query, 'GET'))
            ->withQueryParams($queryParams)
            ->withAttribute('site', $this->createSite('https://example.org/'));
    }

    private function createSite(string $base): Site
    {
        return new Site('main', 1, ['base' => $base, 'languages' => []]);
    }

    private function createHandler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new HtmlResponse('next handler');
            }
        };
    }

    /**
     * Provider that renders "<type>ids</type>" and records the ids it got
     */
    private function createProvider(string $type, ?\Throwable $exception = null): MarkerInfoProviderInterface
    {
        return new class ($type, $exception) implements MarkerInfoProviderInterface {
            public array $calls = [];

            public function __construct(private readonly string $type, private readonly ?\Throwable $exception) {}

            public function getObjectType(): string
            {
                return $this->type;
            }

            public function renderInfo(array $ids, ServerRequestInterface $request, Site $site): string
            {
                $this->calls[] = $ids;
                if ($this->exception !== null) {
                    throw $this->exception;
                }
                return sprintf('<%1$s>%2$s</%1$s>', $this->type, implode(',', $ids));
            }
        };
    }
}
