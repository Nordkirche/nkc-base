<?php

namespace Nordkirche\NkcBase\ViewHelpers\Uri;

use Nordkirche\Ndk\Domain\Model\File\Image;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

// @todo: create a trait which implements the fallback images, so uri. and regular image viewhelepr can share
class ImageViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * Initialize arguments.
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('image', 'object', 'Image', false, null);
        $this->registerArgument('width', 'string', 'Width', false, '');
        $this->registerArgument('height', 'string', 'Height', false, '');
    }

    /**
     * Accepts NDK image objects and requests resized versions
     *
     * @return string
     */
    public function render(): string
    {
        /** @var Image $image */
        $image = $this->arguments['image'];
        $width = $this->arguments['width'];
        $height = $this->arguments['height'];

        if (!$image) {
            return '';
        }

        try {
            return $image->render($width, $height);
        } catch (\Exception $e) {
            return '';
        }
    }
}
