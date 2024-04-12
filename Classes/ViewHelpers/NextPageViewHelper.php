<?php

namespace Nordkirche\NkcBase\ViewHelpers;

use Nordkirche\Ndk\Domain\Query\AbstractQuery;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * NextPageViewHelper
 *
 * = Examples =
 *
 * <code title="Napi Resources ViewHelper">
 * <nkcbase:nextPage query="{query}"></nkcbase:nextPage>
 * </code>
 */
class NextPageViewHelper extends AbstractViewHelper
{
    /**
     * Initialize ViewHelper arguments
     */
    public function initializeArguments()
    {
        $this->registerArgument('query', '\\' . AbstractQuery::class, 'Query object to modify');
    }

    /**
     * @return int
     */
    public function render()
    {
        /** @var AbstractQuery $query */
        $query = $this->arguments['query'];

        if ($query === null) {
            $query = $this->renderChildren();
        }

        return $query->getPageNumber() + 1;
    }
}
