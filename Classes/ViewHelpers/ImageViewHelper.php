<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\ViewHelpers;

use Nordkirche\Ndk\Domain\Model\File\Image;
use Nordkirche\NkcBase\Service\ApiService;
use Nordkirche\NkcBase\Utility\PublicResource;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * Renders an <img> tag for an NDK image in the requested size.
 * All other arguments (alt, class, ...) are rendered as tag attributes.
 *
 * <nkcbase:image image="{person.picture}" width="300c" height="200c" alt="{person.name}" />
 */
class ImageViewHelper extends AbstractTagBasedViewHelper
{
    public const FALLBACK_BASE_PATH = 'EXT:nkc_base/Resources/Public/Images/';

    /**
     * @var string
     */
    protected $tagName = 'img';

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('image', 'object', 'NDK image');
        $this->registerArgument('src', 'string', 'Image URL, used if no image is given');
        $this->registerArgument('width', 'string', 'Width', false, '');
        $this->registerArgument('height', 'string', 'Height', false, '');
        $this->registerArgument('fallbackOnError', 'string', 'Image URL used if the image can not be rendered');
    }

    public function render(): string
    {
        $image = $this->arguments['image'];
        $src = (string)($this->arguments['src'] ?? '');

        if (!$image instanceof Image && $src !== '') {
            $image = ApiService::get()->factory(Image::class);
            $image->setUrl($src);
        }

        try {
            $src = $image instanceof Image ? (string)$image->render((string)$this->arguments['width'], (string)$this->arguments['height']) : '';
        } catch (\InvalidArgumentException $e) {
            $src = $this->arguments['fallbackOnError'] ?? $this->getDevelopmentFallback('invalid_arguments.jpg');
            $this->tag->addAttribute('alt', $e->getMessage());
        } catch (\Exception $e) {
            $src = $this->arguments['fallbackOnError'] ?? $this->getDevelopmentFallback('missing_image.jpg');
            $this->tag->addAttribute('alt', $e->getMessage());
        }

        if ($src === '') {
            return '';
        }

        $this->tag->addAttribute('src', $src);
        return $this->tag->render();
    }

    /**
     * Placeholder images are only shown in development context
     */
    private function getDevelopmentFallback(string $fileName): string
    {
        if (!Environment::getContext()->isDevelopment()) {
            return '';
        }
        return PublicResource::uri(self::FALLBACK_BASE_PATH . $fileName);
    }
}
