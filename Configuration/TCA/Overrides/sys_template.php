<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die();

ExtensionManagementUtility::addStaticFile(
    'nkc_base',
    'Configuration/TypoScript',
    'Nordkirche Client Base Library'
);
