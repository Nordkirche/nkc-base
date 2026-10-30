<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Domain\Repository;

/**
 * Provides the readable label of a NAPI object for the GenericMapper routing aspect
 */
interface GenericRepositoryInterface
{
    /**
     * @param string[] $routeFieldResultNames Field names used in $routeFieldResult, e.g. ['title']
     * @param string $routeFieldResult Pattern like "{title}" or "{address.city}-{name}"
     * @return string The label, or an empty string if none could be found
     */
    public static function getRouteFieldResult(string $object, string $id, array $routeFieldResultNames, string $routeFieldResult): string;
}
