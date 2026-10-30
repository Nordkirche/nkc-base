<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\ViewHelpers;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Adds a meta tag to the page (based on the ViewHelper of EXT:news)
 *
 * <nkcbase:metaTag property="og:title" content="{event.title}" />
 * <nkcbase:metaTag name="keywords" content="{event.keywords}" />
 * <nkcbase:metaTag property="og:url" useCurrentDomain="1" />
 */
class MetaTagViewHelper extends AbstractViewHelper
{
    public function __construct(private readonly PageRenderer $pageRenderer) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('property', 'string', 'Value of the "property" attribute');
        $this->registerArgument('name', 'string', 'Value of the "name" attribute');
        $this->registerArgument('content', 'string', 'Content of the meta tag', false, '');
        $this->registerArgument('useCurrentDomain', 'boolean', 'Use the current URL as content', false, false);
        $this->registerArgument('forceAbsoluteUrl', 'boolean', 'Prepend the site URL to a relative content', false, false);
    }

    public function render(): string
    {
        $request = $this->renderingContext->hasAttribute(ServerRequestInterface::class)
            ? $this->renderingContext->getAttribute(ServerRequestInterface::class)
            : null;

        // Skip records of EXT:news, rendered in another content element (e.g. CType "shortcut")
        $contentObject = $request?->getAttribute('currentContentObject');
        if ($contentObject instanceof ContentObjectRenderer && str_starts_with($contentObject->currentRecord, 'tx_news_domain_model_news:')) {
            return '';
        }

        $normalizedParams = $request?->getAttribute('normalizedParams');
        $content = (string)$this->arguments['content'];

        if ($this->arguments['useCurrentDomain'] && $normalizedParams instanceof NormalizedParams) {
            $content = $normalizedParams->getRequestUrl();
        }

        if ($this->arguments['forceAbsoluteUrl'] && $normalizedParams instanceof NormalizedParams) {
            $parsedUrl = parse_url($content);
            if (is_array($parsedUrl) && !isset($parsedUrl['host'])) {
                $content = rtrim($normalizedParams->getSiteUrl(), '/') . '/' . ltrim($content, '/');
            }
        }

        if ($content === '') {
            return '';
        }

        if (!empty($this->arguments['property'])) {
            $this->pageRenderer->setMetaTag('property', $this->arguments['property'], $content);
        } elseif (!empty($this->arguments['name'])) {
            $this->pageRenderer->setMetaTag('name', $this->arguments['name'], $content);
        }
        return '';
    }
}
