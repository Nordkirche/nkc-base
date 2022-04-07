<?php

use Nordkirche\NkcBase\Controller\AjaxController;
return [
    'NkcBase::suggest' => [
        'path' => '/nkc-base/suggest',
        'target' => AjaxController::class . '::suggest',
    ],
];
