<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Tests\Unit\Service;

use GuzzleHttp\Psr7\Uri;
use Nordkirche\NkcBase\Service\PageUriBuilder;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class PageUriBuilderTest extends UnitTestCase
{
    /**
     * @var list<array{int, array, string, string}>
     */
    private array $routerCalls = [];

    #[Test]
    public function withoutRequestTheUriIsAbsolute(): void
    {
        $uri = $this->createSubject($this->createSite('main'))->build(12, ['tx_foo' => ['uid' => 5]]);

        self::assertSame('https://example.org/page-12', $uri);
        [$pageUid, $parameters, , $type] = $this->routerCalls[0];
        self::assertSame(12, $pageUid);
        self::assertSame(['uid' => 5], $parameters['tx_foo']);
        self::assertInstanceOf(SiteLanguage::class, $parameters['_language']);
        self::assertSame(RouterInterface::ABSOLUTE_URL, $type);
    }

    #[Test]
    public function onTheSameSiteTheUriIsAPath(): void
    {
        $site = $this->createSite('main');
        $request = (new ServerRequest('https://example.org/'))->withAttribute('site', $site);

        self::assertSame('/page-12', $this->createSubject($site)->build(12, [], $request));
        self::assertSame(RouterInterface::ABSOLUTE_PATH, $this->routerCalls[0][3]);
    }

    #[Test]
    public function onAnotherSiteTheUriIsAbsolute(): void
    {
        $request = (new ServerRequest('https://other.org/'))->withAttribute('site', $this->createSite('other'));

        $this->createSubject($this->createSite('main'))->build(12, [], $request);

        self::assertSame(RouterInterface::ABSOLUTE_URL, $this->routerCalls[0][3]);
    }

    #[Test]
    public function withoutPageUidTheCurrentPageIsUsed(): void
    {
        $site = $this->createSite('main');
        $request = (new ServerRequest('https://example.org/'))
            ->withAttribute('site', $site)
            ->withAttribute('routing', new PageArguments(34, '0', []));

        $this->createSubject($site)->build(0, [], $request);

        self::assertSame(34, $this->routerCalls[0][0]);
    }

    #[Test]
    public function pageTypeAndSectionArePassedToTheRouter(): void
    {
        $this->createSubject($this->createSite('main'))->build(12, [], null, false, null, 1700, 'map');

        [, $parameters, $section] = $this->routerCalls[0];
        self::assertSame(1700, $parameters['type']);
        self::assertSame('map', $section);
    }

    #[Test]
    public function unknownPagesGiveNoUri(): void
    {
        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willThrowException(new \RuntimeException('no site'));

        self::assertSame('', (new PageUriBuilder($siteFinder))->build(99));
        self::assertSame('', (new PageUriBuilder($siteFinder))->build(0));
    }

    private function createSubject(Site $site): PageUriBuilder
    {
        $siteFinder = $this->createStub(SiteFinder::class);
        $siteFinder->method('getSiteByPageId')->willReturn($site);
        return new PageUriBuilder($siteFinder);
    }

    private function createSite(string $identifier): Site
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('generateUri')->willReturnCallback(
            function ($route, array $parameters = [], string $fragment = '', string $type = ''): Uri {
                $this->routerCalls[] = [$route, $parameters, $fragment, $type];
                $path = '/page-' . $route;
                return new Uri($type === RouterInterface::ABSOLUTE_URL ? 'https://example.org' . $path : $path);
            }
        );
        $language = new SiteLanguage(0, 'de_DE.UTF-8', new Uri('/'), []);

        $site = $this->createStub(Site::class);
        $site->method('getIdentifier')->willReturn($identifier);
        $site->method('getRouter')->willReturn($router);
        $site->method('getDefaultLanguage')->willReturn($language);
        $site->method('getLanguageById')->willReturn($language);
        return $site;
    }
}
