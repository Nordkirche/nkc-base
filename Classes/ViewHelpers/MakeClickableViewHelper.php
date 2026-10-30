<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Finds the first URL in a text and wraps it in a link
 *
 * <nkcbase:makeClickable>{textWithLinks}</nkcbase:makeClickable>
 */
class MakeClickableViewHelper extends AbstractViewHelper
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

        if (!preg_match('#[-a-zA-Z0-9@:%_\+.~\#?&//=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~\#?&//=]*)?#si', $value, $match)) {
            return $value;
        }

        $url = $match[0];
        $scheme = empty(parse_url($url)['scheme'] ?? '') ? 'http://' : '';
        return str_replace($url, sprintf('<a href="%s%s" target="_blank" rel="nofollow">%s</a>', $scheme, $url, $url), $value);
    }
}
