<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\ViewHelpers;

use Nordkirche\Ndk\Domain\Model\ResourcePlaceholder;
use Nordkirche\Ndk\Service\NapiService;
use Nordkirche\NkcBase\Service\ApiService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Resolves NAPI resource URIs and assigns the objects to a variable
 *
 * <nkcbase:napiResources resourceString="{newsItem.authors}" as="authors">
 *   <f:for each="{authors}" as="author">{author.name.first}</f:for>
 * </nkcbase:napiResources>
 */
class NapiResourcesViewHelper extends AbstractViewHelper
{
    /**
     * @var bool
     */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('resourceString', 'string', 'One or more NAPI resource URIs, separated by comma');
        $this->registerArgument('as', 'string', 'Variable name for the resolved objects', false, 'resources');
    }

    public function render(): mixed
    {
        $collection = [];
        $uris = GeneralUtility::trimExplode(',', (string)$this->arguments['resourceString'], true);

        if ($uris !== []) {
            /** @var NapiService $napiService */
            $napiService = ApiService::get()->factory(NapiService::class);
            foreach ($napiService->resolveUrls($uris) as $object) {
                if (!$object instanceof ResourcePlaceholder) {
                    $collection[] = $object;
                }
            }
        }

        // The variable stays available after the tag (also for self-closing usage)
        $this->renderingContext->getVariableProvider()->add((string)$this->arguments['as'], $collection);
        return $this->renderChildren();
    }
}
