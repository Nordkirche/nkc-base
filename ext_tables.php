<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die('Access denied.');

call_user_func(
    function () {
        ExtensionManagementUtility::addStaticFile('nkc_base', 'Configuration/TypoScript', 'Nordkirche Client Base Library');
    }
);
