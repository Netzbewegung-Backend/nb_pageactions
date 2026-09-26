<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

ExtensionManagementUtility::addUserSetting(
    'nbPageActionsHideAllActionLabels',
    [
        'label' => 'LLL:EXT:nb_pageactions/Resources/Private/Language/locallang_tca.xlf:be_users.nbPageActionsHideAllActionLabels',
        'description' => 'LLL:EXT:nb_pageactions/Resources/Private/Language/locallang_tca.xlf:be_users.nbPageActionsHideAllActionLabels.description',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
        ],
    ],
    'after:contextualRecordEdit'
);
