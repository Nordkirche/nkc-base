<?php

use Nordkirche\NkcBase\Controller\AjaxController;
use Nordkirche\NkcBase\Controller\NapiStatusController;

return [
    'NkcBase::suggest' => [
        'path' => '/nkc-base/suggest',
        'target' => AjaxController::class . '::suggest',
    ],
    'NkcBase::napiHealth' => [
        'path' => '/nkc-base/napi-health',
        'target' => NapiStatusController::class . '::checkAction',
        'methods' => ['POST'],
    ],
];
