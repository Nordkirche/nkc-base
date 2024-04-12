<?php

use Nordkirche\NkcBase\CustomField\Selector;
use Nordkirche\NkcBase\Domain\Repository\GenericRepository;

defined('TYPO3') || die('Access denied.');

$GLOBALS['TYPO3_CONF_VARS']['EXT']['slug']['repository']['nkc_base'] = GenericRepository::class;

// Register node
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1600328581] = [
    'nodeName' => 'napiItemSelector',
    'priority' => 40,
    'class' => Selector::class,
];
