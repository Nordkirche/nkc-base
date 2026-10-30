<?php

use Nordkirche\NkcBase\Controller\NapiStatusController;

return [
    'nkcbase_napistatus' => [
        'parent' => 'system',
        'access' => 'admin',
        'workspaces' => 'live',
        'path' => '/module/system/napi-status',
        'iconIdentifier' => 'module-nkcbase-napistatus',
        'labels' => 'LLL:EXT:nkc_base/Resources/Private/Language/locallang_napistatus_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => NapiStatusController::class . '::indexAction',
            ],
        ],
    ],
];
