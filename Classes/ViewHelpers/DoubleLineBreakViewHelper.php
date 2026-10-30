<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Collapses multiple line breaks into a single one
 *
 * <nkcbase:doubleLineBreak>{text}</nkcbase:doubleLineBreak>
 */
class DoubleLineBreakViewHelper extends AbstractViewHelper
{
    /**
     * @var bool
     */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('value', 'string', 'String to format');
    }

    public function getContentArgumentName(): string
    {
        return 'value';
    }

    public function render(): string
    {
        $value = (string)($this->arguments['value'] ?? $this->renderChildren());
        return (string)preg_replace("/[\r\n]+/", "\n", $value);
    }
}
