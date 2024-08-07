<?php

namespace Nordkirche\NkcBase\CustomField;

use Nordkirche\Ndk\Service\NapiService;
use Nordkirche\NkcBase\Exception\ApiException;
use Nordkirche\NkcBase\Service\ApiService;

class SelectFunction
{
    /**
     * @param $config
     * @return mixed
     * @throws ApiException
     */
    public function createNapiItems($config)
    {
        if ($uri = $config['row']['settings.singlePersonUid']) {
            $api = ApiService::get();
            $napi = $api->factory(NapiService::class);
            $person = $napi->resolveUrl($uri);
            foreach ($person->getFunctions() as $object) {
                if ($object->getInstitution()) {
                    $config['items'][] = [
                        '0' => $object->getInstitution()->getLabel(),
                        '1' => (string)$object,
                    ];
                }
            }
        }
        return $config;
    }
}
