<?php

use Nordkirche\NkcBase\CustomField\Selector;
use Nordkirche\NkcBase\Domain\Repository\GenericRepository;
use Nordkirche\NkcBase\Routing\Aspect\GenericMapper;
use Nordkirche\NkcBase\Routing\Enhancer\PluginPageEnhancer;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

defined('TYPO3') || die();

// Repository used by the GenericMapper routing aspect (key "nkc_base")
$GLOBALS['TYPO3_CONF_VARS']['EXT']['slug']['repository']['nkc_base'] = GenericRepository::class;

$GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['aspects']['GenericMapper'] = GenericMapper::class;
// Extbase enhancer limited to pages with the plugin (used by the route enhancers of the site sets)
$GLOBALS['TYPO3_CONF_VARS']['SYS']['routing']['enhancers']['NkcExtbase'] = PluginPageEnhancer::class;

// FormEngine element "napiItemSelector"
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1600328581] = [
    'nodeName' => 'napiItemSelector',
    'priority' => 40,
    'class' => Selector::class,
];

// Templates of the NAPI status mails
$GLOBALS['TYPO3_CONF_VARS']['MAIL']['templateRootPaths'][1760889600] = 'EXT:nkc_base/Resources/Private/Templates/Email/';

// Cache for map markers. Group "pages" lets the core flush entries by the
// tags tt_content_<uid> and pageId_<pid> whenever an editor changes the record.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['nkcbase_mapmarkers'] ??= [];
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['nkcbase_mapmarkers'] += [
    'frontend' => VariableFrontend::class,
    'backend' => Typo3DatabaseBackend::class,
    'groups' => ['pages'],
    'options' => [
        'defaultLifetime' => 86400,
    ],
];
