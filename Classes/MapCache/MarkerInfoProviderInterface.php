<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\MapCache;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Renders the info window of map markers, requested by the map JavaScript via
 * "{site base}marker?items=i:12,p:5,e:7" (see MarkerInfoMiddleware).
 *
 * Implementations are registered automatically (tag "nkc_base.marker_info_provider").
 * The middleware runs before the page is resolved: there is no TypoScript, so
 * settings have to come from the site settings.
 */
interface MarkerInfoProviderInterface
{
    public const TAG = 'nkc_base.marker_info_provider';

    /**
     * Object type used in the "items" parameter, e.g. "i" for institutions
     */
    public function getObjectType(): string;

    /**
     * @param int[] $ids NAPI ids of the objects
     * @return string HTML
     */
    public function renderInfo(array $ids, ServerRequestInterface $request, Site $site): string;
}
