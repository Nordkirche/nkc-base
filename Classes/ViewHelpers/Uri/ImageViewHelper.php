<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\ViewHelpers\Uri;

use Nordkirche\Ndk\Domain\Model\File\Image;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Returns the URL of an NDK image in the requested size
 *
 * <nkcbase:uri.image image="{person.picture}" width="300c" height="200c" />
 */
class ImageViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('image', 'object', 'NDK image');
        $this->registerArgument('width', 'string', 'Width', false, '');
        $this->registerArgument('height', 'string', 'Height', false, '');
    }

    public function render(): string
    {
        $image = $this->arguments['image'];
        if (!$image instanceof Image) {
            return '';
        }

        try {
            return (string)$image->render((string)$this->arguments['width'], (string)$this->arguments['height']);
        } catch (\Exception) {
            return '';
        }
    }
}
