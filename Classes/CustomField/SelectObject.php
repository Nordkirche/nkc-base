<?php

namespace Nordkirche\NkcBase\CustomField;

use Nordkirche\Ndk\Domain\Query\PageQuery;
use Nordkirche\NkcBase\Service\ApiService;

class SelectObject
{

    /**
     * @param $config
     * @return mixed
     * @throws \Nordkirche\NkcBase\Exception\ApiException
     */
    public function createNapiItems($config)
    {
        $repository = ApiService::getRepository($config['config']['allowed']);
        $query = new PageQuery();
        if ($config['config']['sort']) {
            $query->setSort($config['config']['sort']);
        }
        $items = ApiService::getAllItems($repository, $query);
        foreach ($items as $object) {
            $config['items'][] = [
                '0' => $object->getLabel(),
                '1' => (string)$object
            ];
        }

        return $config;
    }
}
