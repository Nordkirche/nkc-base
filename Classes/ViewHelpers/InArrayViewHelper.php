<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * PHP in_array() for templates, returns 1 or 0
 *
 * <f:if condition="{nkcbase:inArray(haystack: list, needle: 'foo')}">...</f:if>
 */
class InArrayViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('haystack', 'array', 'Array to search in', true);
        $this->registerArgument('needle', 'string', 'Value to search for', true);
        $this->registerArgument('strtolower', 'bool', 'Convert needle to lower case', false, false);
    }

    public function render(): int
    {
        $needle = (string)$this->arguments['needle'];
        if ($this->arguments['strtolower']) {
            $needle = strtolower($needle);
        }
        return in_array($needle, (array)$this->arguments['haystack']) ? 1 : 0;
    }
}
