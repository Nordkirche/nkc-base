<?php

namespace Nordkirche\NkcBase\Domain\Repository;

use Nordkirche\NkcBase\Service\ApiService;
use SIMONKOEHLER\Slug\Domain\Repository\GenericRepositoryInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GenericRepository implements GenericRepositoryInterface
{

    /**
     * @param string $object
     * @param string $id
     * @param array $routeFieldResultNames
     * @param string $routeFieldResult
     * @return string
     * @throws \Nordkirche\NkcBase\Exception\ApiException
     */
    public static function getRouteFieldResult($object, $id, $routeFieldResultNames, $routeFieldResult): string
    {
        $className = sprintf('Nordkirche\Ndk\Domain\Repository\%sRepository', ucfirst($object));
        $api = ApiService::get();
        $repository = $api->factory($className);

        try {
            $item = $repository->getById($id);
        } catch (\Exception $e) {
            $item = false;
        }

        if ($item) {
            if (count($routeFieldResultNames)) {
                $result = self::createRouteResult($item, $routeFieldResultNames, $routeFieldResult);
                return ($result) ? $result : (string)$item->getLabel();
            }
            return (string)$item->getLabel();
        }
        return (string)$id;
    }

    /**
     * @param $result
     * @param array $routeFieldResultNames
     * @param string $routeFieldResult
     * @return string|null
     */
    protected static function createRouteResult($result, $routeFieldResultNames, $routeFieldResult): ?string
    {
        if ($result === null) {
            return $result;
        }
        // Backup object
        $object = $result;

        $substitutes = [];
        foreach ($routeFieldResultNames as $fieldName) {
            // Restore object
            $result = $object;

            $getterName = sprintf('get%s', ucfirst($fieldName));
            if (!method_exists($result, $getterName)) {
                if (strpos($fieldName, '.') !== false) {
                    $fieldNames = GeneralUtility::trimExplode('.', $fieldName);
                    $objectGetterName = sprintf('get%s', ucfirst($fieldNames[0]));
                    $getterName =   sprintf('get%s', ucfirst($fieldNames[1]));
                }
                if (!method_exists($result, $objectGetterName)) {
                    return null;
                }
                $result = $result->$objectGetterName();
            }
            $routeFieldName = '{' . $fieldName . '}';
            $substitutes[$routeFieldName] = $result->$getterName();
        }
        return str_replace(
            array_keys($substitutes),
            array_values($substitutes),
            $routeFieldResult
        );
    }
}
