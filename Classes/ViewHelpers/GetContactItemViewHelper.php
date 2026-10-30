<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\ViewHelpers;

use Nordkirche\Ndk\Domain\Model\ContactItem;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders the children for the first (or every) contact item of a given type
 *
 * <nkcbase:getContactItem items="{institution.contactItems}" type="E-Mail" as="mail">{mail.value}</nkcbase:getContactItem>
 */
class GetContactItemViewHelper extends AbstractViewHelper
{
    /**
     * @var bool
     */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('items', 'iterable', 'Contact items', true);
        $this->registerArgument('type', 'string', 'Contact type', true);
        $this->registerArgument('as', 'string', 'Variable name of the item', false, 'item');
        $this->registerArgument('renderAllItems', 'boolean', 'Render all matching items, not only the first one', false, false);
    }

    public function render(): string
    {
        $type = $this->arguments['type'];
        $as = $this->arguments['as'];
        $items = $this->arguments['items'];
        $items = is_array($items) ? $items : iterator_to_array($items);

        $items = array_filter($items, static fn($item): bool => $item instanceof ContactItem && $item->getType() === $type);
        if (!$this->arguments['renderAllItems']) {
            $items = array_slice($items, 0, 1);
        }

        $variableProvider = $this->renderingContext->getVariableProvider();
        $content = '';
        foreach ($items as $item) {
            $variableProvider->add($as, $item);
            $content .= $this->renderChildren();
            $variableProvider->remove($as);
        }
        return $content;
    }
}
