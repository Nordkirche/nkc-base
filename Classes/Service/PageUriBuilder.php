<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * Builds a page URI with the router of the target site.
 *
 * Unlike the Extbase UriBuilder this works without a frontend request, e.g. when
 * map markers are rendered on the command line or in a middleware. Without a
 * request, or if the target page is on another site, the URI is absolute.
 */
class PageUriBuilder
{
    public function __construct(private readonly SiteFinder $siteFinder) {}

    /**
     * @param int $pageUid Target page, 0 = current page of the request
     * @param array $parameters Query parameters of the URI
     * @param int|null $language Language id, defaults to the current language
     * @return string Empty if the page or its site can't be resolved
     */
    public function build(
        int $pageUid,
        array $parameters = [],
        ?ServerRequestInterface $request = null,
        bool $absolute = false,
        ?int $language = null,
        int $pageType = 0,
        string $section = ''
    ): string {
        if ($pageUid <= 0) {
            $pageUid = (int)($request?->getAttribute('routing')?->getPageId() ?? 0);
        }
        if ($pageUid <= 0) {
            return '';
        }

        try {
            $site = $this->siteFinder->getSiteByPageId($pageUid);
        } catch (\Throwable) {
            return '';
        }

        if ($pageType > 0) {
            $parameters['type'] = $pageType;
        }
        $parameters['_language'] = $this->resolveLanguage($site, $request, $language);

        // Absolute if requested, without a request (CLI) or if the target is on another site
        $currentSite = $request?->getAttribute('site');
        $absolute = $absolute
            || $request === null
            || !$currentSite instanceof Site
            || $currentSite->getIdentifier() !== $site->getIdentifier();

        try {
            return (string)$site->getRouter()->generateUri(
                $pageUid,
                $parameters,
                $section,
                $absolute ? RouterInterface::ABSOLUTE_URL : RouterInterface::ABSOLUTE_PATH
            );
        } catch (\Throwable) {
            return '';
        }
    }

    private function resolveLanguage(Site $site, ?ServerRequestInterface $request, ?int $language): SiteLanguage
    {
        if ($language !== null) {
            try {
                return $site->getLanguageById($language);
            } catch (\Throwable) {
            }
        }
        $currentLanguage = $request?->getAttribute('language');
        if ($currentLanguage instanceof SiteLanguage) {
            try {
                return $site->getLanguageById($currentLanguage->getLanguageId());
            } catch (\Throwable) {
            }
        }
        return $site->getDefaultLanguage();
    }
}
