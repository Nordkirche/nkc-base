<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Domain\Repository;

use Nordkirche\NkcBase\Service\ApiService;

class GenericRepository implements GenericRepositoryInterface
{
    public static function getRouteFieldResult(string $object, string $id, array $routeFieldResultNames, string $routeFieldResult): string
    {
        try {
            $item = ApiService::getRepository($object)->getById((int)$id);
        } catch (\Throwable) {
            $item = null;
        }

        if (!is_object($item)) {
            return $id;
        }

        if ($routeFieldResultNames !== []) {
            $result = self::createRouteResult($item, $routeFieldResultNames, $routeFieldResult);
            if ($result !== null && $result !== '') {
                return $result;
            }
        }
        return method_exists($item, 'getLabel') ? (string)$item->getLabel() : $id;
    }

    /**
     * Replaces placeholders like {title} or {address.city} with the values of the object.
     * Returns null if a placeholder can't be resolved.
     */
    protected static function createRouteResult(object $item, array $routeFieldResultNames, string $routeFieldResult): ?string
    {
        $substitutes = [];
        foreach ($routeFieldResultNames as $fieldName) {
            $value = $item;
            foreach (explode('.', $fieldName) as $propertyName) {
                $getterName = 'get' . ucfirst(trim($propertyName));
                if (!is_object($value) || !method_exists($value, $getterName)) {
                    return null;
                }
                $value = $value->$getterName();
            }
            if (!is_scalar($value) && !$value instanceof \Stringable) {
                return null;
            }
            $substitutes['{' . $fieldName . '}'] = (string)$value;
        }
        return strtr($routeFieldResult, $substitutes);
    }
}
