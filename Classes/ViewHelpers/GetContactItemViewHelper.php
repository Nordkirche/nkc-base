<?php

namespace Nordkirche\NkcBase\ViewHelpers;

use Nordkirche\Ndk\Domain\Model\ContactItem;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * If a contact item is found, this viewhelper will render its children and provide the item in a variable.
 * Else it will do nothing and will not render its children.
 *
 * Example:
 * <nkc:getContactItem items="{person.contactItems}" type="Website">
 *  Website: {item.value}
 * </nkc>
 */
class GetContactItemViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('items', 'object', 'Contact Items', true);
        $this->registerArgument('type', 'string', 'Contact type', true);
        $this->registerArgument('as', 'string', 'Alias', false, 'item');
        $this->registerArgument('renderAllItems', 'boolean', 'if true not only the first but all contact items are rendered', false, false);
    }

    /**
     * @return string
     */
    public function render(): string
    {
        $content = '';

        $type = $this->arguments['type'];
        $as = $this->arguments['as'];
        $renderAllItems = $this->arguments['renderAllItems'];

        $items = iterator_to_array($this->arguments['items']);
        $items = array_filter($items, function (ContactItem $item) use ($type) {
            return $item->getType() === $type;
        });

        if ($renderAllItems === false) {
            $items = array_slice($items, 0, 1);
        }

        foreach ($items as $item) {
            $this->templateVariableContainer->add($as, $item);
            $content .= $this->renderChildren();
            $this->templateVariableContainer->remove($as);
        }

        return $content;
    }
}
