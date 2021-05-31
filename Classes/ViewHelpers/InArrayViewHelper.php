<?php

namespace Nordkirche\NkcBase\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class InArrayViewHelper extends AbstractViewHelper
{
    public function initializeArguments()
    {
        $this->registerArgument('haystack', 'array', 'View helper haystack ', true);
        $this->registerArgument('needle', 'string', 'View helper needle', true);
        $this->registerArgument('strtolower', 'bool', 'Convert needle to lower case', false);
    }

    // php in_array viewhelper
    public function render()
    {
        $needle = $this->arguments['needle'];
        $haystack = $this->arguments['haystack'];

        if ($this->arguments['strtolower']) {
            $needle = strtolower($needle);
        }

        return in_array($needle, $haystack) ? 1 : 0;
    }
}
