<?php

use Nordkirche\NkcBase\Command\MapCacheCommandController;
use Nordkirche\NkcBase\Command\NapiSyncCommandController;

return [
    'nkc_base:map_cache' => [
        'class' => MapCacheCommandController::class,
        'schedulable' => true,
    ],
    'nkc_base:napi_sync' => [
        'class' => NapiSyncCommandController::class,
        'schedulable' => true,
    ],
];
