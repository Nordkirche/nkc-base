<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Controller;

use Nordkirche\Ndk\Api;
use Nordkirche\Ndk\Domain\Query\EventQuery;
use Nordkirche\Ndk\Domain\Query\InstitutionQuery;
use Nordkirche\Ndk\Domain\Query\PageQuery;
use Nordkirche\Ndk\Domain\Query\PersonQuery;
use Nordkirche\Ndk\Service\Exception\ClientException;
use Nordkirche\Ndk\Service\NapiService;
use Nordkirche\Ndk\Service\Result;
use Nordkirche\NkcBase\Exception\ApiException;
use Nordkirche\NkcBase\Service\ApiService;
use Nordkirche\NkcBase\Service\QueryFilter;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;

/**
 * Base controller of the NAPI extensions
 */
class BaseController extends ActionController
{
    /**
     * @var Api
     */
    protected $api;

    /**
     * @var NapiService
     */
    protected $napiService;

    /**
     * @throws ApiException
     */
    public function initializeAction(): void
    {
        $this->api = ApiService::get();
        $this->napiService = $this->api->factory(NapiService::class);

        // Merge TypoScript defaults and flexform
        if (!empty($this->settings['flexformDefault']) && is_array($this->settings['flexformDefault'])) {
            if (!empty($this->settings['flexform']) && is_array($this->settings['flexform'])) {
                ArrayUtility::mergeRecursiveWithOverrule($this->settings['flexformDefault'], $this->settings['flexform'], true, false);
            }
            $this->settings['flexform'] = $this->settings['flexformDefault'];
        }
    }

    /**
     * Set pagination parameters in query
     *
     * @param PageQuery $query
     */
    protected function setPagination($query, $currentPage, bool $mergedResult = false): void
    {
        $limit = $this->getItemsPerPage($mergedResult);

        if ($this->isPaginationActive()) {
            $query->setPageSize($limit);
            if ((int)$currentPage > 0) {
                $query->setPageNumber((int)$currentPage);
            }
        } elseif ($limit > 0) {
            $query->setPageSize($limit);
        }
    }

    /**
     * @return array{0: int, 1: int} [startRow, numRows]
     */
    protected function getPaginationValues($currentPage, bool $mergedResult = false): array
    {
        $numRows = $this->getItemsPerPage($mergedResult);
        $startRow = 0;

        if ($this->isPaginationActive()) {
            $page = max((int)$currentPage, 1);
            $startRow = ($page - 1) * $this->getItemsPerPage();
        }

        return [$startRow, $numRows];
    }

    /**
     * Items per page if the pagination is active, otherwise the general item limit
     */
    protected function getItemsPerPage(bool $mergedResult = false): int
    {
        if ($this->isPaginationActive()) {
            $numRows = (int)($this->settings['flexform']['paginate']['itemsPerPage'] ?? 0)
                ?: (int)($this->settings['paginate']['itemsPerPage'] ?? 0);
        } else {
            $numRows = (int)($this->settings['flexform']['maxItems'] ?? 0)
                ?: (int)($this->settings['maxItems'] ?? 0);
        }

        return $mergedResult ? (int)floor($numRows / 2) : $numRows;
    }

    protected function getCurrentPage($currentPage): int
    {
        return (int)$currentPage;
    }

    protected function getNumberOfPages($numItems, $itemsPerPage = 0): int
    {
        $numRows = (int)$itemsPerPage ?: $this->getItemsPerPage();
        if ($numRows <= 0) {
            return 1;
        }
        return max(1, (int)ceil((int)$numItems / $numRows));
    }

    protected function isPaginationActive(): bool
    {
        return (int)($this->settings['flexform']['paginate']['mode'] ?? 0) > 0;
    }

    /**
     * @param InstitutionQuery|PersonQuery|EventQuery $query
     */
    public function setGeoFilter($query, $geosearch, $latitude, $longitude, $radius): void
    {
        QueryFilter::geo($query, $geosearch, $latitude, $longitude, $radius);
    }

    /**
     * @param InstitutionQuery $query
     */
    public function setInstitutionFilter($query, $filter, $selectOption): void
    {
        QueryFilter::institutions($query, $filter, $selectOption);
    }

    public function setInstitutionTypeFilter($query, $filter): void
    {
        QueryFilter::institutionTypes($query, $filter);
    }

    /**
     * @param PersonQuery $query
     */
    public function setPersonInstitutionFilter($query, $filter): void
    {
        QueryFilter::personInstitutions($query, $filter);
    }

    public function setFunctionTypeFilter($query, $filter): void
    {
        QueryFilter::functionTypes($query, $filter);
    }

    public function setPersonFilter($query, $filter): void
    {
        QueryFilter::persons($query, $filter);
    }

    public function setAvailableFunctionFilter($query, $filter): void
    {
        QueryFilter::availableFunctions($query, $filter);
    }

    /**
     * Uid of the current page (replaces $GLOBALS['TSFE']->id)
     */
    protected function getCurrentPageId(): int
    {
        $pageInformation = $this->request->getAttribute('frontend.page.information');
        if (is_object($pageInformation) && method_exists($pageInformation, 'getId')) {
            return (int)$pageInformation->getId();
        }
        $routing = $this->request->getAttribute('routing');
        return is_object($routing) && method_exists($routing, 'getPageId') ? (int)$routing->getPageId() : 0;
    }

    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    protected function getTypoScriptConfiguration(): array
    {
        $typoScript = $this->configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
        return GeneralUtility::makeInstance(TypoScriptService::class)->convertTypoScriptArrayToPlainArray($typoScript);
    }

    /**
     * Result of a list query. If the NAPI rejects the query (HTTP 4xx, e.g. a filter
     * the endpoint doesn't support), the list is empty instead of an error page and
     * the page is not cached.
     *
     * @param object $repository NDK repository
     */
    protected function fetchList(object $repository, PageQuery $query): Result
    {
        try {
            return $repository->get($query);
        } catch (ClientException $exception) {
            GeneralUtility::makeInstance(LogManager::class)->getLogger(static::class)->warning(
                'The NAPI rejected a list query: {message}',
                ['message' => $exception->getMessage(), 'exception' => $exception]
            );
            // Don't keep the empty list in the page cache: the NAPI may only be down for a moment
            $cacheInstruction = isset($this->request) ? $this->request->getAttribute('frontend.cache.instruction') : null;
            if ($cacheInstruction instanceof CacheInstruction) {
                $cacheInstruction->disableCache('EXT:nkc_base: NAPI error in a list query');
            }
            return new Result(new \SplObjectStorage(), 0, 0, []);
        }
    }

    /**
     * Layout of the list items: plugin (settings.flexform.templateLayout), TypoScript
     * (settings.templateLayout) or "Default"
     */
    protected function getTemplateLayout(): string
    {
        $layout = (string)($this->settings['flexform']['templateLayout'] ?? '') ?: (string)($this->settings['templateLayout'] ?? '');
        $layout = (string)preg_replace('/[^A-Za-z0-9]/', '', $layout);
        return $layout !== '' ? $layout : 'Default';
    }

    /**
     * Partial of the list items for the layout: <base>/<Layout> if it exists, <base> otherwise.
     * Layouts are added per page TSconfig (Nordkirche\NkcBase\Backend\TemplateLayouts).
     */
    protected function getLayoutPartial(string $basePartial): string
    {
        $layout = $this->getTemplateLayout();
        if ($layout === 'Default') {
            return $basePartial;
        }
        $partial = $basePartial . '/' . $layout;
        try {
            $renderingContext = method_exists($this->view, 'getRenderingContext') ? $this->view->getRenderingContext() : null;
            $renderingContext?->getTemplatePaths()->getPartialPathAndFilename($partial);
            return $partial;
        } catch (\Throwable) {
            return $basePartial;
        }
    }

    /**
     * Builds the page list for the NAPI pagination partial
     *
     * @param Result $result
     */
    protected function getPagination($result, $currentPage): array
    {
        $currentPage = max((int)$currentPage, 1);
        $pages = $this->getNumberOfPages($result->getRecordCount());

        if ($pages <= 1) {
            return [];
        }

        if ($pages > 10) {
            $lower = $currentPage - 3;
            $upper = $currentPage + 3;
            if ($lower < 1) {
                $upper -= $lower;
                $lower = 1;
            }
        } else {
            $lower = 1;
            $upper = $pages;
        }

        $pageList = ['pages' => []];
        for ($i = 1; $i <= $pages; $i++) {
            $inWindow = $i >= $lower && $i <= $upper;
            $isTail = $upper < $pages && $i >= $pages - 2;
            $isHead = $lower > 2 && $i <= 2;
            if (!$inWindow && !$isTail && !$isHead) {
                continue;
            }
            if ($upper < $pages - 3 && $i === $pages - 2) {
                $pageList['pages'][] = ['index' => '...', 'gap' => 1];
            }
            $pageList['pages'][] = ['index' => $i, 'current' => $i === $currentPage ? 1 : 0];
            if ($lower > 3 && $i === 2) {
                $pageList['pages'][] = ['index' => '...', 'gap' => 1];
            }
        }

        if ($currentPage > 1) {
            $pageList['prev'] = ['index' => $currentPage - 1];
        }
        if ($currentPage < $pages) {
            $pageList['next'] = ['index' => $currentPage + 1];
        }

        return $pageList;
    }

    /**
     * Resolves the template/partial/layout root paths of a view configuration
     *
     * @return string[]
     */
    protected function getPath(array $config, string $type, string $extensionKey): array
    {
        $paths = [];
        foreach ((array)($config['view'][$type . 'RootPaths'] ?? []) as $path) {
            $paths[] = GeneralUtility::getFileAbsFileName($path);
        }
        if ($paths === []) {
            $paths[] = GeneralUtility::getFileAbsFileName(sprintf('EXT:%s/Resources/Private/%ss/', $extensionKey, ucfirst($type)));
        }
        return $paths;
    }
}
