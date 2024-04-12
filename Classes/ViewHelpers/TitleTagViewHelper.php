<?php

namespace Nordkirche\NkcBase\ViewHelpers;

use Nordkirche\NkcBase\Provider\TitleProvider;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * ViewHelper to render the page title
 *
 * # Example: Basic Example
 * # Description: Render the content of the VH as page title
 * <code>
 *    <n:titleTag>{newsItem.title}</n:titleTag>
 * </code>
 * <output>
 *    <title>TYPO3 is awesome</title>
 * </output>
 */
class TitleTagViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        $content = trim($renderChildrenClosure());
        if (!empty($content)) {
            GeneralUtility::makeInstance(TitleProvider::class)->setTitle($content);
        }
    }
}
