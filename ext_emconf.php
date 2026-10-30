<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Nordkirche NAPI Client Base Library',
    'description' => 'Basis-Funktionen für Extensions, die die Nordkirche API (NAPI) konsumieren',
    'category' => 'be',
    'author' => 'netzleuchten GmbH',
    'author_email' => 'hallo@netzleuchten.com',
    'state' => 'stable',
    'version' => '13.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '13.4.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [
            'scheduler' => '',
        ],
    ],
];
