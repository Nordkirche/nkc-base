<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\ViewHelpers;

use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Adds the content to the <head> of the page
 *
 * <nkcbase:headerData><link rel="alternate" href="..." /></nkcbase:headerData>
 */
class HeaderDataViewHelper extends AbstractViewHelper
{
    /**
     * @var bool
     */
    protected $escapeChildren = false;

    public function __construct(private readonly PageRenderer $pageRenderer) {}

    public function render(): string
    {
        $this->pageRenderer->addHeaderData((string)$this->renderChildren());
        return '';
    }
}
