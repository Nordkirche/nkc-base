<?php

namespace  Nordkirche\NkcBase\ViewHelpers;

use Nordkirche\Ndk\Service\Result;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * OrderByPropertyViewHelper
 *
 * = Examples =
 *
 * <code title="Napi Resources ViewHelper">
 * <f:for each="{nkcbase:OrderByProperty({collection: institutions, property: 'name', order: 'Name1,Name3,Name2'})" as="institution">
 *   {institution.name}<br />
 * </f:for>
 * </code>
 */
class OrderByPropertyViewHelper extends AbstractViewHelper
{
    /**
     * Initialize arguments.
     *
     * @throws Exception
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('collection', 'mixed', 'The collection to be ordered');
        $this->registerArgument('property', 'string', 'The property you want to order by');
        $this->registerArgument('order', 'string', 'Optional manual order');
    }

    /**
     * @return array
     */
    public function render()
    {
        /** @var Result $collection */
        $collection = $this->arguments['collection'];
        $getterName = 'get' . ucfirst($this->arguments['property']);
        $indexName = $this->arguments['property'];
        $order = $this->arguments['order'];

        if ($collection === null) {
            $collection = $this->renderChildren();
        }

        $doOrdering = ($collection instanceof Result) ? ($collection->count() > 0) : ($collection instanceof \Countable && (count($collection) > 0));

        $newCollection = [];

        if ($doOrdering) {
            if ($order == '') {
                foreach ($collection as $index => $item) {
                    if (is_array($item)) {
                        $newCollection[$item[$indexName] . '-' . $index] = $item;
                    } else {
                        $newCollection[$item->$getterName() . '-' . $index] = $item;
                    }
                }
                ksort($newCollection);
            } else {
                $addedItems = [];
                foreach (GeneralUtility::trimExplode(',', $order) as $orderItem) {
                    foreach ($collection as $index => $item) {
                        if (is_array($item)) {
                            if ($item[$indexName] == $orderItem) {
                                $addedItems[$index] = true;
                                $newCollection[$item[$indexName] . '-' . $index] = $item;
                            }
                        } else {
                            if ($item->$getterName() == $orderItem) {
                                $addedItems[$index] = true;
                                $newCollection[$item->$getterName() . '-' . $index] = $item;
                            }
                        }
                    }
                }

                // Add all other items
                foreach ($collection as $index => $item) {
                    if (!isset($addedItems[$index])) {
                        if (is_array($item)) {
                            $newCollection[$item[$indexName] . '-' . $index] = $item;
                        } else {
                            $newCollection[$item->$getterName() . '-' . $index] = $item;
                        }
                    }
                }
            }
        }

        return $newCollection;
    }
}
