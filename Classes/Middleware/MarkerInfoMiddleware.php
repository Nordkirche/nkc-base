<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Middleware;

use Nordkirche\NkcBase\MapCache\MarkerInfoProviderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Info windows of map markers: GET {site base}marker?items=i:12,p:5,e:7
 */
final class MarkerInfoMiddleware implements MiddlewareInterface
{
    private const PATH = 'marker';
    private const MAX_ITEMS = 100;

    /**
     * @param iterable<MarkerInfoProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(MarkerInfoProviderInterface::TAG)]
        private readonly iterable $providers,
        private readonly LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site || $request->getMethod() !== 'GET' || !$this->isMarkerPath($request, $site)) {
            return $handler->handle($request);
        }

        $items = $request->getQueryParams()['items'] ?? '';
        $itemsByType = $this->parseItems(is_string($items) ? $items : '');
        $html = '';
        foreach ($this->providers as $provider) {
            $ids = $itemsByType[$provider->getObjectType()] ?? [];
            if ($ids === []) {
                continue;
            }
            try {
                $html .= $provider->renderInfo($ids, $request, $site);
            } catch (\Throwable $e) {
                $this->logger->error('Map marker info could not be rendered: ' . $e->getMessage(), ['type' => $provider->getObjectType(), 'ids' => $ids]);
            }
        }

        return new HtmlResponse($html, 200, [
            'Cache-Control' => 'public, max-age=600',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    private function isMarkerPath(ServerRequestInterface $request, Site $site): bool
    {
        $basePath = rtrim($site->getBase()->getPath(), '/') . '/';
        $path = $request->getUri()->getPath();
        if ($path === $basePath . self::PATH || $path === '/' . self::PATH) {
            return true;
        }
        // Language prefixes, e.g. /en/marker
        $language = $request->getAttribute('language');
        if ($language !== null && method_exists($language, 'getBase')) {
            return $path === rtrim($language->getBase()->getPath(), '/') . '/' . self::PATH;
        }
        return false;
    }

    /**
     * "i:12,p:5,i:13" => ['i' => [12, 13], 'p' => [5]]
     *
     * @return array<string, int[]>
     */
    private function parseItems(string $items): array
    {
        $result = [];
        foreach (array_slice(explode(',', $items), 0, self::MAX_ITEMS) as $item) {
            if (preg_match('/^([a-z]):(\d+)$/', trim($item), $matches)) {
                $result[$matches[1]][] = (int)$matches[2];
            }
        }
        return array_map(static fn(array $ids): array => array_values(array_unique($ids)), $result);
    }
}
