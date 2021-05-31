<?php

defined('TYPO3_MODE') || die('Access denied.');

$GLOBALS['TYPO3_CONF_VARS']['EXT']['slug']['repository']['nkc_base'] = \Nordkirche\NkcBase\Domain\Repository\GenericRepository::class;

// Register node
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1600328581] = [
    'nodeName' => 'napiItemSelector',
    'priority' => 40,
    'class' => \Nordkirche\NkcBase\CustomField\Selector::class,
];
