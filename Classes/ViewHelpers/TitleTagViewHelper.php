<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\ViewHelpers;

use Nordkirche\NkcBase\Provider\TitleProvider;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Sets the page title via the page title provider "nkcBase"
 *
 * <nkcbase:titleTag>{event.title}</nkcbase:titleTag>
 */
class TitleTagViewHelper extends AbstractViewHelper
{
    public function __construct(private readonly TitleProvider $titleProvider) {}

    public function render(): string
    {
        $content = trim((string)$this->renderChildren());
        if ($content !== '') {
            $this->titleProvider->setTitle($content);
        }
        return '';
    }
}
