<?php

namespace Nordkirche\NkcBase\ViewHelpers;

use Nordkirche\NkcBase\Exception\ApiException;
use Nordkirche\NkcBase\Service\ApiService;
use TYPO3\CMS\Core\Core\Environment;
use Nordkirche\Ndk\Domain\Model\File\Image;
use TYPO3\CMS\Core\Core\ApplicationContext;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

class ImageViewHelper extends AbstractTagBasedViewHelper
{
    const FALLBACK_BASE_PATH = '/typo3conf/ext/nkc_base/Resources/Public/Images/';

    /**
     * @var string
     */
    protected $tagName = 'img';

    /**
     * Initialize arguments.
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('image', 'object', 'Image', false, null);
        $this->registerArgument('src', 'string', 'Image url', false, null);
        $this->registerArgument('width', 'string', 'Width', false, '');
        $this->registerArgument('height', 'string', 'Height', false, '');
        $this->registerArgument('fallbackOnError', 'string', 'fallback on Error', false, '');

        $this->registerUniversalTagAttributes();
        $this->registerTagAttribute('alt', 'string', 'Specifies an alternate text for an image', false);
        $this->registerTagAttribute('ismap', 'string', 'Specifies an image as a server-side image-map. Rarely used. Look at usemap instead', false);
        $this->registerTagAttribute('longdesc', 'string', 'Specifies the URL to a document that contains a long description of an image', false);
        $this->registerTagAttribute('usemap', 'string', 'Specifies an image as a client-side image-map', false);
    }

    /**
     * Accepts NDK image objects and requests resized versions
     *
     * @return string
     * @throws ApiException
     */
    public function render(): string
    {
        /** @var Image $image */
        $image = $this->arguments['image'];
        $src = $this->arguments['src'];
        $width = $this->arguments['width'];
        $height = $this->arguments['height'];
        $fallbackOnError = $this->arguments['fallbackOnError'];

        if (!$image && $src) {
            $image = ApiService::get()->factory(Image::class);
            $image->setUrl($src);
        }

        try {
            $src = ($image instanceof Image) ? $image->render($width, $height) : '';
        } catch (\InvalidArgumentException $e) {
            $src = $fallbackOnError ?? $this->getFallbackImages(Environment::getContext())['INVALID_ARGUMENTS'] ?? '';
            $this->tag->addAttribute('alt', $e->getMessage());
        } catch (\Exception $e) {
            $src = $fallbackOnError ?? $this->getFallbackImages(Environment::getContext())['MISSING_IMAGE'] ?? '';
            $this->tag->addAttribute('alt', $e->getMessage());
        }

        if (empty($src)) {
            return '';
        }

        $this->tag->addAttribute('src', $src);

        return $this->tag->render();
    }

    /**
     * @param ApplicationContext $context
     * @return array
     */
    private function getFallbackImages(ApplicationContext $context)
    {
        if ($context->isDevelopment()) {
            return [
                'INVALID_ARGUMENTS' => self::FALLBACK_BASE_PATH . 'invalid_arguments.jpg',
                'MISSING_IMAGE' => self::FALLBACK_BASE_PATH . 'missing_image.jpg'
            ];
        }
        return [
                'INVALID_ARGUMENTS' => '',
                'MISSING_IMAGE' => ''
            ];
    }
}
