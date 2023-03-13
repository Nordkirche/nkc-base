<?php

defined('TYPO3') || die('Access denied.');

call_user_func(
    function () {
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('nkc_base', 'Configuration/TypoScript', 'Nordkirche Client Base Library');
    }
);
