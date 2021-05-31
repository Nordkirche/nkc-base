<?php

namespace Nordkirche\NkcBase\CustomField;

use Nordkirche\NkcBase\Service\ApiService;

class SelectFunction
{

    /**
     * @param $config
     * @return mixed
     * @throws \Nordkirche\NkcBase\Exception\ApiException
     */
    public function createNapiItems($config)
    {
        if ($uri = $config['row']['settings.singlePersonUid']) {
            $api = ApiService::get();
            $napi = $api->factory(\Nordkirche\Ndk\Service\NapiService::class);
            $person = $napi->resolveUrl($uri);
            foreach ($person->getFunctions() as $object) {
                if ($object->getInstitution()) {
                    $config['items'][] = [
                        '0' => $object->getInstitution()->getLabel(),
                        '1' => (string)$object
                    ];
                }
            }
        }
        return $config;
    }
}
