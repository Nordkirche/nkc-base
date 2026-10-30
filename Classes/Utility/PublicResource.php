<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Utility;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\SystemResource\Publishing\SystemResourcePublisherInterface;
use TYPO3\CMS\Core\SystemResource\SystemResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;

/**
 * Public URL of an extension resource ("EXT:my_ext/Resources/Public/...") for TYPO3 v13 and v14
 */
final class PublicResource
{
    public static function uri(string $resource, ?ServerRequestInterface $request = null): string
    {
        try {
            // TYPO3 v14: system resource API (also resolves folders)
            if (interface_exists(SystemResourcePublisherInterface::class)) {
                $publicResource = GeneralUtility::makeInstance(SystemResourceFactory::class)->createPublicResource($resource);
                return (string)GeneralUtility::makeInstance(SystemResourcePublisherInterface::class)->generateUri($publicResource, $request);
            }
            return PathUtility::getPublicResourceWebPath($resource);
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Whether the resource exists ("EXT:..." path)
     */
    public static function exists(string $resource): bool
    {
        $absolutePath = GeneralUtility::getFileAbsFileName($resource);
        return $absolutePath !== '' && is_file($absolutePath);
    }
}
