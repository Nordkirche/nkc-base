<?php

namespace Nordkirche\NkcBase\ViewHelpers;

/**
 * Simple viewhelper to get the NAPI class name of a model
 *
 * = Example =
 *
 * <code title="Example">
 *  <f:className model="{institition}" />
 * </code>
 */

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class ClassNameViewHelper extends AbstractViewHelper
{
    /**
     * Initialize arguments.
     *
     * @throws \TYPO3Fluid\Fluid\Core\ViewHelper\Exception
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('model', 'object', 'model to check');
    }

    /**
     * @return bool|string
     */
    public function render()
    {
        $model = $this->arguments['model'];
        if ($model === null) {
            $model = $this->renderChildren();
        }
        if (is_object($model)) {
            try {
                $reflect = new \ReflectionClass($model);
            } catch (\Exception $e) {
                return false;
            }
            return $reflect->getShortName();
        }
        return false;
    }
}
