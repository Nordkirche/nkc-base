<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\MapCache;

use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Builds the map markers of one content element type.
 *
 * Implementations are registered automatically (tag "nkc_base.map_marker_provider")
 * and are used by MapMarkerCache, both in the frontend and by the command
 * "nkc_base:map_cache". As the command runs without a frontend request, a provider
 * must not depend on one: settings that are not part of the flexform should be
 * read from the site settings ($site->getSettings()).
 */
interface MapMarkerProviderInterface
{
    public const TAG = 'nkc_base.map_marker_provider';

    /**
     * Content element types handled by this provider: the CType of the plugin,
     * or the former list_type value (e.g. "nkcaddress_map").
     *
     * @return string[]
     */
    public function getSupportedPluginTypes(): array;

    /**
     * @param array<string, mixed> $contentRow Record of tt_content
     * @param array<string, mixed> $settings Plugin settings (in the command: the "settings" part of the flexform)
     * @return array<int|string, mixed> The markers, stored as "data" in the cache entry
     */
    public function buildMarkers(array $contentRow, array $settings, Site $site): array;
}
