<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\MapCache;

use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Cache for the markers of map content elements.
 *
 * Usage in a controller action:
 *
 *   $entry = $this->mapMarkerCache->get($contentObject->data, $this->settings, $request->getAttribute('site'));
 *   return $this->jsonResponse(json_encode($entry));
 *
 * Entries are tagged with tt_content_<uid> and pageId_<pid>, so they are
 * flushed automatically when an editor changes the content element or clears
 * the page cache.
 */
class MapMarkerCache
{
    private const ENTRY_PREFIX = 'content_';

    /**
     * @var array<string, MapMarkerProviderInterface>|null
     */
    private ?array $providersByType = null;

    /**
     * @param iterable<MapMarkerProviderInterface> $providers
     */
    public function __construct(
        private readonly FrontendInterface $cache,
        private readonly iterable $providers,
    ) {}

    /**
     * Returns the cache entry ['crdate' => timestamp, 'data' => markers] of a content element.
     * The markers are built if there is no entry, if it is older than $maxAge
     * seconds (0 = no limit) or if $force is set.
     *
     * @param array<string, mixed> $contentRow
     * @param array<string, mixed> $settings
     * @return array{crdate: int, data: array}
     */
    public function get(array $contentRow, array $settings, Site $site, int $maxAge = 0, bool $force = false): array
    {
        $identifier = $this->getIdentifier($contentRow);
        $entry = $this->cache->get($identifier);

        if (!$force && $this->isUsable($entry, $maxAge)) {
            return $entry;
        }

        $provider = $this->getProvider($contentRow);
        if ($provider === null) {
            throw new \InvalidArgumentException(
                sprintf('No map marker provider for content element %d (type "%s")', $contentRow['uid'] ?? 0, self::getPluginType($contentRow)),
                1760889600
            );
        }

        $entry = [
            'crdate' => time(),
            'data' => $provider->buildMarkers($contentRow, $settings, $site),
        ];
        $this->cache->set($identifier, $entry, $this->getTags($contentRow));

        return $entry;
    }

    /**
     * Whether a fresh entry exists (younger than $maxAge seconds, 0 = no limit)
     *
     * @param array<string, mixed> $contentRow
     */
    public function has(array $contentRow, int $maxAge = 0): bool
    {
        return $this->isUsable($this->cache->get($this->getIdentifier($contentRow)), $maxAge);
    }

    /**
     * @param array<string, mixed> $contentRow
     */
    public function remove(array $contentRow): void
    {
        $this->cache->remove($this->getIdentifier($contentRow));
    }

    /**
     * @param array<string, mixed> $contentRow
     */
    public function getProvider(array $contentRow): ?MapMarkerProviderInterface
    {
        return $this->getProvidersByType()[self::getPluginType($contentRow)] ?? null;
    }

    /**
     * @return string[] All content element types with a registered provider
     */
    public function getSupportedPluginTypes(): array
    {
        return array_keys($this->getProvidersByType());
    }

    /**
     * Plugin identifier of a content element: the CType, or the list_type for
     * plugins of type "list" (TYPO3 v13 and older).
     *
     * @param array<string, mixed> $contentRow
     */
    public static function getPluginType(array $contentRow): string
    {
        $cType = (string)($contentRow['CType'] ?? '');
        return $cType === 'list' ? (string)($contentRow['list_type'] ?? '') : $cType;
    }

    /**
     * @param mixed $entry
     */
    private function isUsable($entry, int $maxAge): bool
    {
        if (!is_array($entry) || !isset($entry['crdate'], $entry['data'])) {
            return false;
        }
        return $maxAge <= 0 || (int)$entry['crdate'] > time() - $maxAge;
    }

    /**
     * @param array<string, mixed> $contentRow
     */
    private function getIdentifier(array $contentRow): string
    {
        return self::ENTRY_PREFIX . (int)($contentRow['uid'] ?? 0);
    }

    /**
     * @param array<string, mixed> $contentRow
     * @return string[]
     */
    private function getTags(array $contentRow): array
    {
        return [
            'tt_content_' . (int)($contentRow['uid'] ?? 0),
            'pageId_' . (int)($contentRow['pid'] ?? 0),
        ];
    }

    /**
     * @return array<string, MapMarkerProviderInterface>
     */
    private function getProvidersByType(): array
    {
        if ($this->providersByType === null) {
            $this->providersByType = [];
            foreach ($this->providers as $provider) {
                foreach ($provider->getSupportedPluginTypes() as $type) {
                    $this->providersByType[$type] = $provider;
                }
            }
        }
        return $this->providersByType;
    }
}
