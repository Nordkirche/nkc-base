<?php

namespace  Nordkirche\NkcBase\ViewHelpers;

use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithContentArgumentAndRenderStatic;

/**
 * Simple viewhelper to find uris in a text and wrap them as links
 *
 * = Example =
 *
 * <code title="Example">
 * <f:findLinks>{text_with_links}</f:findLinks>
 * </code>
 */
class MakeClickableViewHelper extends AbstractViewHelper
{
    use CompileWithContentArgumentAndRenderStatic;

    /**
     * @var bool
     */
    protected $escapeOutput = false;

    /**
     * Initialize arguments.
     *
     * @throws \TYPO3Fluid\Fluid\Core\ViewHelper\Exception
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('value', 'string', 'string to format');
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param \TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface $renderingContext
     * @return string
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        $value = $renderChildrenClosure();

        preg_match_all('#[-a-zA-Z0-9@:%_\+.~\#?&//=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~\#?&//=]*)?#si', $value, $result);

        foreach ($result as $matches) {
            foreach ($matches as $match) {
                if (strlen($match) > 0) {
                    $urlParts = parse_url($match);
                    $scheme = (!$urlParts['scheme']) ? 'http://' : '';
                    $value = str_replace($match, sprintf('<a href="%s%s" target="_blank" rel="nofollow">%s</a>', $scheme, $match, $match), $value);
                    return $value;
                }
            }
        }
        return $value;
    }
}
