<?php

use Nordkirche\NkcBase\Middleware\MarkerInfoMiddleware;

return [
    'frontend' => [
        'nordkirche/nkc-base/marker-info' => [
            'target' => MarkerInfoMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/base-redirect-resolver',
            ],
        ],
    ],
];
