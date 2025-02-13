<?php

namespace Nordkirche\NkcBase\Domain\Repository;

interface GenericRepositoryInterface {

    /**
     * @param string $object
     * @param string $id
     * @param array $routeFieldResultNames
     * @param string $routeFieldResult
     * @return string
     */
    public static function getRouteFieldResult(string $object, string $id, array $routeFieldResultNames, string $routeFieldResult): string;

}
