<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\ViewHelpers;

use Nordkirche\Ndk\Domain\Query\PageQuery;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Returns the number of the page following the one of the query
 *
 * <nkcbase:nextPage query="{query}" />
 */
class NextPageViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('query', PageQuery::class, 'NDK query');
    }

    public function getContentArgumentName(): string
    {
        return 'query';
    }

    public function render(): int
    {
        $query = $this->arguments['query'] ?? $this->renderChildren();
        return $query instanceof PageQuery ? $query->getPageNumber() + 1 : 1;
    }
}
