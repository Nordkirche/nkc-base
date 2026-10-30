<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\CustomField;

use Nordkirche\Ndk\Domain\Query\PageQuery;
use Nordkirche\NkcBase\Service\ApiService;

/**
 * itemsProcFunc: all NAPI objects of one type as select items
 *
 *   'itemsProcFunc' => \Nordkirche\NkcBase\CustomField\SelectObject::class . '->createNapiItems',
 *   'allowed' => 'institutionType',
 *   'sort' => 'name',
 */
class SelectObject
{
    public function createNapiItems(array &$config): array
    {
        $repository = ApiService::getRepository((string)($config['config']['allowed'] ?? ''));
        $query = new PageQuery();
        if (!empty($config['config']['sort'])) {
            $query->setSort($config['config']['sort']);
        }

        foreach (ApiService::getAllItems($repository, $query) as $object) {
            $config['items'][] = [
                'label' => (string)$object->getLabel(),
                'value' => (string)$object,
            ];
        }
        return $config;
    }
}
