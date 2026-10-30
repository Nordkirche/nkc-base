<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Routing\Enhancer;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Routing\Route;
use TYPO3\CMS\Core\Routing\RouteCollection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Routing\ExtbasePluginEnhancer;

/**
 * Extbase route enhancer whose routes only exist on pages with the plugin
 *
 * Route enhancers of site sets can't know the page ids (limitToPages). Without a limit,
 * the plugins of a site share their routes on every page: "/seite/2" of the person list
 * would win on the page of the event list, too.
 *
 *   routeEnhancers:
 *     NkcEventList:
 *       type: NkcExtbase          # instead of Extbase, same options
 *       extension: NkcEvent
 *       plugin: List
 *       onlyOnPluginPages: true   # default; false: like Extbase
 *
 * The plugin is found by its CType (nkcevent_list) in tt_content of the page.
 * Plugins shown on other pages (e.g. "Insert records") need onlyOnPluginPages: false
 * or limitToPages.
 */
class PluginPageEnhancer extends ExtbasePluginEnhancer
{
    public function enhanceForMatching(RouteCollection $collection): void
    {
        $defaultPageRoute = $collection->get('default');
        if (!$defaultPageRoute instanceof Route || $this->isOnPluginPage($defaultPageRoute)) {
            parent::enhanceForMatching($collection);
        }
    }

    protected function isOnPluginPage(Route $defaultPageRoute): bool
    {
        if (!filter_var($this->configuration['onlyOnPluginPages'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }
        $page = $defaultPageRoute->getOption('_page');
        if (!is_array($page)) {
            return true;
        }
        $pageId = (int)(($page['l10n_parent'] ?? 0) ?: ($page['uid'] ?? 0));
        return in_array($this->getContentType(), $this->getContentTypesOfPage($pageId), true);
    }

    /**
     * CType of the plugin, as registered by ExtensionUtility::configurePlugin()
     */
    protected function getContentType(): string
    {
        $extension = str_replace('_', '', (string)($this->configuration['extension'] ?? ''));
        return strtolower($extension) . '_' . strtolower((string)($this->configuration['plugin'] ?? ''));
    }

    /**
     * @return list<string>
     */
    protected function getContentTypesOfPage(int $pageId): array
    {
        $cache = $this->getRuntimeCache();
        $identifier = 'nkcbase_route_ctypes_' . $pageId;
        $contentTypes = $cache->get($identifier);
        if (is_array($contentTypes)) {
            return $contentTypes;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tt_content');
        // Hidden or timed elements still own their URLs: a 404 would be cached by search engines
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $contentTypes = array_values(array_map('strval', $queryBuilder
            ->select('CType')
            ->from('tt_content')
            ->where($queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)))
            ->groupBy('CType')
            ->executeQuery()
            ->fetchFirstColumn()));

        $cache->set($identifier, $contentTypes);
        return $contentTypes;
    }

    protected function getRuntimeCache(): FrontendInterface
    {
        return GeneralUtility::makeInstance(CacheManager::class)->getCache('runtime');
    }
}
