<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Orders a collection (objects or arrays) by a property.
 * With "order" the given values come first, in this order.
 *
 * <f:for each="{nkcbase:orderByProperty(collection: persons, property: 'name', order: 'Pastor,Diakon')}" as="person">
 */
class OrderByPropertyViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('collection', 'mixed', 'The collection to be ordered');
        $this->registerArgument('property', 'string', 'The property to order by', true);
        $this->registerArgument('order', 'string', 'Optional manual order (comma separated values)', false, '');
    }

    public function getContentArgumentName(): string
    {
        return 'collection';
    }

    public function render(): array
    {
        $collection = $this->arguments['collection'] ?? $this->renderChildren();
        if (!is_iterable($collection) || ($collection instanceof \Countable && count($collection) === 0)) {
            return [];
        }

        $property = (string)$this->arguments['property'];
        $order = (string)$this->arguments['order'];

        $items = [];
        foreach ($collection as $index => $item) {
            $items[$index] = [$this->getPropertyValue($item, $property), $item];
        }

        $sorted = [];
        if ($order === '') {
            foreach ($items as $index => [$value, $item]) {
                $sorted[$value . '-' . $index] = $item;
            }
            ksort($sorted);
            return $sorted;
        }

        // Items matching the manual order first, then all others in their original order
        foreach (GeneralUtility::trimExplode(',', $order) as $orderValue) {
            foreach ($items as $index => [$value, $item]) {
                if ($value == $orderValue) {
                    $sorted[$value . '-' . $index] = $item;
                    unset($items[$index]);
                }
            }
        }
        foreach ($items as $index => [$value, $item]) {
            $sorted[$value . '-' . $index] = $item;
        }
        return $sorted;
    }

    private function getPropertyValue(mixed $item, string $property): string
    {
        if (is_array($item)) {
            return (string)($item[$property] ?? '');
        }
        $getter = 'get' . ucfirst($property);
        return is_object($item) && method_exists($item, $getter) ? (string)$item->$getter() : '';
    }
}
