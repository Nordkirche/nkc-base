<?php

namespace  Nordkirche\NkcBase\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * NapiResourcesViewHelper
 *
 * = Examples =
 *
 * <code title="Napi Resources ViewHelper">
 * <nkcbase:napiResources resourceString="{newsItem.authors}" as="authors">
 *   <f:for each="{authors}" as="author">
 *     {author.name.first}
 *   </f:for>
 * </nkcbase:napiResources> * </code>
 */
class NapiResourcesViewHelper extends AbstractViewHelper
{
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
        $this->registerArgument(
            'resourceString',
            'string',
            'The Napi Resource string (one or multiple resources separated by comma)'
        );
        $this->registerArgument(
            'as',
            'string',
            'Template variable name to assign; if not specified the ViewHelper returns the variable instead.'
        );
    }

    /**
     * Returns the given resources from the Napi
     *
     * @return mixed
     */
    public function render()
    {
        $collection = [];

        $as = isset($this->arguments['as']) ? $this->arguments['as'] : 'resources';

        /** @var \Nordkirche\Ndk\Service\NapiService $api */
        $api = \Nordkirche\NkcBase\Service\ApiService::get()->factory(\Nordkirche\Ndk\Service\NapiService::class);

        $resources = $api->resolveUrls(explode(',', $this->arguments['resourceString']));

        foreach ($resources as $object) {
            if (!($object instanceof \Nordkirche\Ndk\Domain\Model\ResourcePlaceholder)) {
                $collection[] = $object;
            }
        }

        $templateVariableContainer = $this->renderingContext->getVariableProvider();
        $templateVariableContainer->add($as, $collection);
        return $this->renderChildren();
    }
}
