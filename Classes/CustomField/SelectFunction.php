<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\CustomField;

use Nordkirche\Ndk\Domain\Model\Person\Person;
use Nordkirche\Ndk\Service\NapiService;
use Nordkirche\NkcBase\Service\ApiService;

/**
 * itemsProcFunc: the institutions of the functions of the person selected in
 * the flexform field "settings.singlePersonUid"
 */
class SelectFunction
{
    public function createNapiItems(array &$config): array
    {
        $uri = $config['row']['settings.singlePersonUid'] ?? '';
        if (is_array($uri)) {
            $uri = reset($uri);
        }
        if (!is_string($uri) || $uri === '') {
            return $config;
        }

        /** @var NapiService $napiService */
        $napiService = ApiService::get()->factory(NapiService::class);
        $person = $napiService->resolveUrl($uri);
        if (!$person instanceof Person) {
            return $config;
        }
        foreach ($person->getFunctions() as $function) {
            if ($function->getInstitution()) {
                $config['items'][] = [
                    'label' => (string)$function->getInstitution()->getLabel(),
                    'value' => (string)$function,
                ];
            }
        }
        return $config;
    }
}
