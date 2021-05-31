<?php

    return [
        'nkc_base:map_cache' => [
            'class' => \Nordkirche\NkcBase\Command\MapCacheCommandController::class,
            'schedulable' => true,
        ],
        'nkc_base:napi_sync' => [
            'class' => \Nordkirche\NkcBase\Command\NapiSyncCommandController::class,
            'schedulable' => true,
        ],
    ];