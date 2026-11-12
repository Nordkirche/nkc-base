<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\ViewHelpers\Link;

use Nordkirche\NkcBase\Service\PageUriBuilder;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * Link to a page, built with the router of the target site.
 *
 * Unlike <f:link.page> this works without a frontend request, e.g. when map
 * markers are rendered on the command line by the map cache warm-up. In that
 * case the link is always absolute. See PageUriBuilder.
 *
 * <nkcbase:link.page pageUid="12" additionalParams="{tx_nkcaddress_person: {uid: person.id}}">Mehr erfahren</nkcbase:link.page>
 */
class PageViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * @var string
     */
    protected $tagName = 'a';

    public function __construct(private readonly PageUriBuilder $pageUriBuilder)
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
        return $this->pageUriBuilder->build(
            (int)($this->arguments['pageUid'] ?? 0),
            (array)$this->arguments['additionalParams'],
            $this->getRequest(),
            (bool)$this->arguments['absolute'],
            isset($this->arguments['language']) ? (int)$this->arguments['language'] : null,
            (int)$this->arguments['pageType'],
            (string)$this->arguments['section']
        );
    }

    private function getRequest(): ?ServerRequestInterface
    {
        return $this->renderingContext->hasAttribute(ServerRequestInterface::class)
            ? $this->renderingContext->getAttribute(ServerRequestInterface::class)
            : null;
    }
}
