<?php

namespace Nordkirche\NkcBase\Controller;

use Nordkirche\Ndk\Api;
use Nordkirche\Ndk\Domain\Model\Geocode;
use Nordkirche\Ndk\Domain\Query\PageQuery;
use Nordkirche\Ndk\Service\NapiService;
use Nordkirche\NkcBase\Service\ApiService;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class BaseController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{

    /**
     * @var \Nordkirche\Ndk\Api
     */
    protected $api;

    /**
     * @var \Nordkirche\Ndk\Service\NapiService
     */
    protected $napiService;

    public function initializeAction()
    {
        $this->api = ApiService::get();
        $this->napiService = $this->api->factory(NapiService::class);

        // Merge TS and flexform
        if (isset($this->settings['flexformDefault']) && count($this->settings['flexformDefault'])) {
            if (isset($this->settings['flexform']) && count($this->settings['flexform'])) {
                ArrayUtility::mergeRecursiveWithOverrule($this->settings['flexformDefault'], $this->settings['flexform'], true, false);
            }
            $this->settings['flexform'] = $this->settings['flexformDefault'];
        }
    }

    /**
     * Set pagination parameters in query
     *
     * @param \Nordkirche\Ndk\Domain\Query\AbstractQuery $query
     * @param int $currentPage
     * @param bool $mergedResult
     */
    protected function setPagination($query, $currentPage, $mergedResult = false)
    {
        // Limit items per api request
        if ((int)$this->settings['flexform']['paginate']['mode'] > 0) {
            // Pagination is active: use page limit
            $limit = $this->settings['flexform']['paginate']['itemsPerPage'] ?: $this->settings['paginate']['itemsPerPage'];
            $query->setPageSize($mergedResult ? floor($limit / 2) : $limit);

            // Set page
            if ($pagination = $this->getWidgetValues()) {
                $query->setPageNumber($pagination['currentPage']);
            } elseif ($currentPage) {
                $query->setPageNumber($currentPage);
            }
        } else {
            // Pagination is inactive: use general limit
            $limit = $this->settings['flexform']['maxItems'] ?: $this->settings['maxItems'];
            if ($limit) {
                $query->setPageSize($mergedResult ? floor($limit / 2) : $limit);
            }
        }
    }

    /**
     * Helper to get the widget values
     * @return array|bool
     */
    protected function getWidgetValues()
    {
        for ($i = 0; $i <= 10; $i++) {
            if ($this->request->hasArgument('@widget_' . $i)) {
                return $this->request->getArgument('@widget_' . $i);
            }
        }
        return false;
    }

    /**
     * Helper to get pagination values
     *
     * @param $currentPage
     * @param bool $mergedResult
     * @return array
     */
    protected function getPaginationValues($currentPage, $mergedResult = false)
    {
        $startRow = 0;

        // Limit items per api request
        if ((int)$this->settings['flexform']['paginate']['mode'] > 0) {
            // Pagination is active: use page limit
            $numRows = $this->settings['flexform']['paginate']['itemsPerPage'] ?: $this->settings['paginate']['itemsPerPage'];
            $startRow = ($this->getCurrentPage($currentPage) ? ($this->getCurrentPage($currentPage) - 1) : 0) * $numRows;
        } else {
            // Pagination is inactive: use general limit
            $numRows = $this->settings['flexform']['maxItems'] ?: $this->settings['maxItems'];
        }

        $numRows = $mergedResult ? floor($numRows / 2) : $numRows;

        return [$startRow, $numRows];
    }

    /**
     * @param bool $mergedResult
     * @return float
     */
    protected function getItemsPerPage($mergedResult = false)
    {
        if ((int)$this->settings['flexform']['paginate']['mode'] > 0) {
            // Pagination is active: use page limit
            $numRows = $this->settings['flexform']['paginate']['itemsPerPage'] ?: $this->settings['paginate']['itemsPerPage'];
        } else {
            // Pagination is inactive: use general limit
            $numRows = $this->settings['flexform']['maxItems'] ?: $this->settings['maxItems'];
        }

        return $mergedResult ? floor($numRows / 2) : $numRows;
    }

    /**
     * @param $currentPage
     * @return mixed
     */
    protected function getCurrentPage($currentPage)
    {
        $pagination = $this->getWidgetValues();

        if ($pagination) {
            return $pagination['currentPage'];
        }

        return $currentPage;
    }

    /**
     * @param $numItems
     * @param $itemsPerPage
     * @return float
     */
    protected function getNumberOfPages($numItems, $itemsPerPage = 0)
    {
        if ($itemsPerPage) {
            $numRows = $itemsPerPage;
        } else {
            if ((int)$this->settings['flexform']['paginate']['mode'] > 0) {
                $numRows = $this->settings['flexform']['paginate']['itemsPerPage'] ?: $this->settings['paginate']['itemsPerPage'];
            } else {
                $numRows = $this->settings['flexform']['maxItems'] ?: $this->settings['maxItems'];
            }
        }
        return floor($numItems / $numRows) + 1;
    }

    /**
     * @param PageQuery $query
     * @param $geosearch
     * @param $latitude
     * @param $longitude
     * @param $radius
     */
    public function setGeoFilter($query, $geosearch, $latitude, $longitude, $radius)
    {
        if ($geosearch == 1) {
            $latitude = (float)$latitude;
            $longitude = (float)$longitude;
            $radius = (float)$radius;

            if (($latitude != 0) && ($longitude != 0) && ($radius != 0)) {
                $geoCode = new Geocode($latitude, $longitude, $radius);
                $query->setGeocode($geoCode);
            }
        }
    }

    /**
     * Set filter in query
     *
     * @param \Nordkirche\Ndk\Domain\Query\InstitutionQuery $query
     * @param string $filter
     * @param string $selectOption
     */
    public function setInstitutionFilter($query, $filter, $selectOption)
    {
        if ($filter) {
            // Which filter has to be used?
            switch ($selectOption) {
                case 'CHILDREN':    // Show children of selected
                    $resourceArray = GeneralUtility::trimExplode(',', $filter);
                    $query->setParentInstitutions($resourceArray);
                    break;

                case 'CHILDREN_RECURSIVE':    // Show all children of selected
                    $resourceArray = GeneralUtility::trimExplode(',', $filter);
                    $query->setAncestorInstitutions($resourceArray);
                    break;

                case 'BOTH':   // Show selected and children
                    $resourceArray = GeneralUtility::trimExplode(',', $filter);
                    $query->setParentInstitutionsOrSelf($resourceArray);

                    break;

                case 'BOTH_RECURSIVE':   // Show selected and all children
                    $resourceArray = GeneralUtility::trimExplode(',', $filter);
                    $query->setAncestorInstitutionsOrSelf($resourceArray);

                    break;

                default:    // Show selected
                    $resourceArray = GeneralUtility::trimExplode(',', $filter);
                    $query->setInstitutions($resourceArray);
            }
        }
    }

    /**
     * @param $query
     * @param string $filter
     */
    public function setInstitutionTypeFilter($query, $filter)
    {
        if ($filter) {
            $resourceArray = GeneralUtility::trimExplode(',', $filter);
            $query->setInstitutionType($resourceArray);
        }
    }

    /**
     * Set filter in query
     *
     * @param \Nordkirche\Ndk\Domain\Query\PersonQuery $query
     * @param string $filter
     */
    public function setPersonInstitutionFilter($query, $filter)
    {
        if ($filter) {
            $resourceArray = GeneralUtility::trimExplode(',', $filter);
            $query->setInstitutions($resourceArray);
        }
    }

    /**
     * @param $query
     * @param $filter
     */
    public function setFunctionTypeFilter($query, $filter)
    {
        if ($filter) {
            $resourceArray = GeneralUtility::trimExplode(',', $filter);
            $query->setFunctions($resourceArray);
        }
    }

    /**
     * @param $query
     * @param $filter
     */
    public function setPersonFilter($query, $filter)
    {
        if ($filter) {
            $resourceArray = GeneralUtility::trimExplode(',', $filter);
            $query->setPersons($resourceArray);
        }
    }

    /**
     * @param $query
     * @param $filter
     */
    public function setAvailableFunctionFilter($query, $filter)
    {
        if ($filter) {
            $resourceArray = GeneralUtility::trimExplode(',', $filter);
            $query->setAvailableFunctions($resourceArray);
        }
    }

    /**
     * @param $settings
     */
    public function setSettings($settings)
    {
        $this->settings = $settings;
    }

    public function getTypoScriptConfiguration()
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $configurationManager = $objectManager->get(\TYPO3\CMS\Extbase\Configuration\ConfigurationManager::class);
        $typoScriptService = $objectManager->get(TypoScriptService::class);
        return $typoScriptService->convertTypoScriptArrayToPlainArray($configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT));
    }
}
