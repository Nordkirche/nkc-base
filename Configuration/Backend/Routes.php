<?php

use Nordkirche\NkcBase\Wizard\NapiWizardController;

return [
    // Search wizard of the NAPI item selector (opened in a modal)
    'wizard_napi' => [
        'path' => '/nkc-base/wizard',
        'target' => NapiWizardController::class,
    ],
];
