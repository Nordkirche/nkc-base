<?php

use TYPO3\CMS\Core\Information\Typo3Version;

defined('TYPO3') || die();

$ll = 'LLL:EXT:nkc_base/Resources/Private/Language/locallang_db.xlf:';

$tca = [
    'ctrl' => [
        'title' => $ll . 'tx_slug_mapping',
        'label' => 'slug',
        'label_alt' => 'object_name,object_id',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'uid',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'rootLevel' => 1,
        'iconfile' => 'EXT:nkc_base/Resources/Public/Icons/Extension.svg',
        'security' => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'columns' => [
        'object_name' => [
            'label' => $ll . 'tx_slug_mapping.object_name',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'required' => true,
                'eval' => 'trim',
            ],
        ],
        'object_id' => [
            'label' => $ll . 'tx_slug_mapping.object_id',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'required' => true,
                'eval' => 'trim',
            ],
        ],
        'slug' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:pages.slug',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'max' => 255,
                'required' => true,
                'eval' => 'trim',
            ],
        ],
    ],
    'types' => [
        0 => [
            'showitem' => 'object_name, object_id, slug, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access, hidden',
        ],
    ],
];

// TYPO3 v14 derives searchable fields from the column types
if ((new Typo3Version())->getMajorVersion() < 14) {
    $tca['ctrl']['searchFields'] = 'object_name,object_id,slug';
}

return $tca;
