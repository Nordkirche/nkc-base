<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Returns the short class name of an object, e.g. "Institution"
 *
 * <nkcbase:className model="{item}" />
 */
class ClassNameViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('model', 'object', 'Object to inspect');
    }

    public function getContentArgumentName(): string
    {
        return 'model';
    }

    public function render(): string|false
    {
        $model = $this->arguments['model'] ?? $this->renderChildren();
        return is_object($model) ? (new \ReflectionClass($model))->getShortName() : false;
    }
}
