<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Provider;

use TYPO3\CMS\Core\PageTitle\AbstractPageTitleProvider;

/**
 * Page title set by the NAPI extensions (see TitleTagViewHelper)
 */
class TitleProvider extends AbstractPageTitleProvider
{
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
