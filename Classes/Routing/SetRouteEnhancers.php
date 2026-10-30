<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Routing;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\Event\SiteConfigurationBeforeWriteEvent;
use TYPO3\CMS\Core\Configuration\Event\SiteConfigurationLoadedEvent;
use TYPO3\CMS\Core\Configuration\Loader\YamlFileLoader;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Site\Set\SetDefinition;
use TYPO3\CMS\Core\Site\Set\SetRegistry;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Route enhancers of site sets (route-enhancers.yaml next to the config.yaml of a set)
 *
 * TYPO3 14 merges them into the site configuration itself. For TYPO3 13 this class does
 * the same, with the same rules: sets in dependency order, the site configuration wins.
 *
 * On both versions the presets are removed again before the site configuration is
 * written (backend module "Sites"), so they don't end up in the config.yaml.
 */
final class SetRouteEnhancers
{
    private const FILE_NAME = 'route-enhancers.yaml';

    public function __construct(
        private readonly SetRegistry $setRegistry,
        private readonly YamlFileLoader $yamlFileLoader,
    ) {}

    #[AsEventListener('nkc-base/set-route-enhancers')]
    public function addPresets(SiteConfigurationLoadedEvent $event): void
    {
        if (self::isSupportedByCore()) {
            return;
        }
        $configuration = $event->getConfiguration();
        $presets = $this->getPresets((array)($configuration['dependencies'] ?? []));
        if ($presets === []) {
            return;
        }
        $configuration['routeEnhancers'] = ArrayUtility::replaceAndAppendScalarValuesRecursive(
            $presets,
            (array)($configuration['routeEnhancers'] ?? [])
        );
        $event->setConfiguration($configuration);
    }

    #[AsEventListener('nkc-base/set-route-enhancers-before-write')]
    public function removePresets(SiteConfigurationBeforeWriteEvent $event): void
    {
        $configuration = $event->getConfiguration();
        if (!is_array($configuration['routeEnhancers'] ?? null)) {
            return;
        }

        // The loaded configuration is based on the file as it is now
        $fileName = Environment::getConfigPath() . '/sites/' . $event->getSiteIdentifier() . '/config.yaml';
        $file = is_file($fileName) ? $this->yamlFileLoader->load(GeneralUtility::fixWindowsFilePath($fileName), 0) : [];
        $fileEnhancers = is_array($file['routeEnhancers'] ?? null) ? $file['routeEnhancers'] : [];
        $presets = $this->getPresets((array)($file['dependencies'] ?? []));

        foreach ($configuration['routeEnhancers'] as $key => $enhancer) {
            if (!isset($presets[$key])) {
                continue;
            }
            $loaded = ArrayUtility::replaceAndAppendScalarValuesRecursive((array)$presets[$key], (array)($fileEnhancers[$key] ?? []));
            if ($enhancer != $loaded) {
                // Changed by someone: keep it
                continue;
            }
            if (array_key_exists($key, $fileEnhancers)) {
                $configuration['routeEnhancers'][$key] = $fileEnhancers[$key];
            } else {
                unset($configuration['routeEnhancers'][$key]);
            }
        }

        if ($configuration['routeEnhancers'] === [] && !array_key_exists('routeEnhancers', $file)) {
            unset($configuration['routeEnhancers']);
        }
        $event->setConfiguration($configuration);
    }

    /**
     * @param array<mixed> $dependencies Set names
     * @return array<string, mixed>
     */
    public function getPresets(array $dependencies): array
    {
        $setNames = array_values(array_filter($dependencies, 'is_string'));
        $presets = [];
        foreach ($this->setRegistry->getSets(...$setNames) as $set) {
            $presets = ArrayUtility::replaceAndAppendScalarValuesRecursive($presets, $this->getRouteEnhancers($set));
        }
        return $presets;
    }

    /**
     * TYPO3 14.1+ loads the files of the sets itself
     */
    public static function isSupportedByCore(): bool
    {
        return property_exists(SetDefinition::class, 'routeEnhancers');
    }

    /**
     * @return array<string, mixed>
     */
    private function getRouteEnhancers(SetDefinition $set): array
    {
        if (self::isSupportedByCore()) {
            /** @phpstan-ignore property.notFound (TYPO3 14) */
            return (array)$set->routeEnhancers;
        }

        // TYPO3 13: "typoscript" is the folder of the set, unless the set configures another one
        $folder = $set->typoscript !== null ? GeneralUtility::getFileAbsFileName($set->typoscript) : '';
        $fileName = $folder !== '' ? rtrim($folder, '/') . '/' . self::FILE_NAME : '';
        if ($fileName === '' || !is_file($fileName)) {
            return [];
        }
        $content = $this->yamlFileLoader->load($fileName, YamlFileLoader::PROCESS_IMPORTS | YamlFileLoader::ALLOW_EMPTY_FILE);
        return is_array($content['routeEnhancers'] ?? null) ? $content['routeEnhancers'] : [];
    }
}
