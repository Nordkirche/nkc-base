<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Provider;

use TYPO3\CMS\Core\PageTitle\AbstractPageTitleProvider;

/**
 * Generate page title
 */
class TitleProvider extends AbstractPageTitleProvider
{
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }
}
