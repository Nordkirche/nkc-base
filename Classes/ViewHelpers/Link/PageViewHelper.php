<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\ViewHelpers\Link;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * Link to a page, built with the router of the target site.
 *
 * Unlike <f:link.page> this works without a frontend request, e.g. when map
 * markers are rendered on the command line by the map cache warm-up. In that
 * case the link is always absolute.
 *
 * <nkcbase:link.page pageUid="12" additionalParams="{tx_nkcaddress_person: {uid: person.id}}">Mehr erfahren</nkcbase:link.page>
 */
class PageViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * @var string
     */
    protected $tagName = 'a';

    public function __construct(private readonly SiteFinder $siteFinder)
    {
        parent::__construct();
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('pageUid', 'string', 'Target page');
        $this->registerArgument('additionalParams', 'array', 'Query parameters of the URI', false, []);
        $this->registerArgument('pageType', 'integer', 'Page type of the target page', false, 0);
        $this->registerArgument('section', 'string', 'Anchor of the URI', false, '');
        $this->registerArgument('absolute', 'bool', 'Create an absolute URI', false, false);
        $this->registerArgument('language', 'mixed', 'Language id, defaults to the current language');
    }

    public function render(): string
    {
        $uri = $this->buildUri();
        if ($uri === '') {
            return (string)$this->renderChildren();
        }
        $this->tag->addAttribute('href', $uri);
        $this->tag->setContent((string)$this->renderChildren());
        $this->tag->forceClosingTag(true);
        return $this->tag->render();
    }

    private function buildUri(): string
    {
        $pageUid = (int)($this->arguments['pageUid'] ?? 0);
        $request = $this->getRequest();

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

        $parameters = (array)$this->arguments['additionalParams'];
        if ((int)$this->arguments['pageType'] > 0) {
            $parameters['type'] = (int)$this->arguments['pageType'];
        }
        $parameters['_language'] = $this->resolveLanguage($site, $request);

        // Absolute if requested, without a request (CLI) or if the target is on another site
        $currentSite = $request?->getAttribute('site');
        $absolute = $this->arguments['absolute']
            || $request === null
            || !$currentSite instanceof Site
            || $currentSite->getIdentifier() !== $site->getIdentifier();

        try {
            return (string)$site->getRouter()->generateUri(
                $pageUid,
                $parameters,
                (string)$this->arguments['section'],
                $absolute ? RouterInterface::ABSOLUTE_URL : RouterInterface::ABSOLUTE_PATH
            );
        } catch (\Throwable) {
            return '';
        }
    }

    private function resolveLanguage(Site $site, ?ServerRequestInterface $request): SiteLanguage
    {
        if (isset($this->arguments['language'])) {
            try {
                return $site->getLanguageById((int)$this->arguments['language']);
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

    private function getRequest(): ?ServerRequestInterface
    {
        return $this->renderingContext->hasAttribute(ServerRequestInterface::class)
            ? $this->renderingContext->getAttribute(ServerRequestInterface::class)
            : null;
    }
}
