<?php

use Taketool\Sysinfo\Controller\CheckRemotePageController;
use Taketool\Sysinfo\Controller\Mod1Controller;
use TYPO3\CMS\Core\Utility\VersionNumberUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3_MODE') || die();

(static function () {
    $isT3v9 = VersionNumberUtility::convertVersionNumberToInteger(TYPO3_version) < 10000000;
    $extensionName = $isT3v9
        ? 'Taketool.Sysinfo'
        : 'Sysinfo';

    if ($isT3v9) // <v10
    {
        ExtensionUtility::registerModule(
            $extensionName,
            'tools',
            'm1',
            'top',
            [
                'Mod1' => 'allTemplates,checkDomains,deleteFile,securityCheck,shaOne,plugins,rootTemplates,viewFile',
                'CheckRemotePage'=> 'index',
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:sysinfo/Resources/Public/Icons/user_mod_m1.svg',
                'labels' => 'LLL:EXT:sysinfo/Resources/Private/Language/locallang_m1.xlf',
            ]
        );
    } else { // >= v10
        ExtensionUtility::registerModule(
            $extensionName,
            'tools',
            'm1',
            'top',
            [
                Mod1Controller::class => 'allTemplates,checkDomains,deleteFile,securityCheck,shaOne,plugins,rootTemplates,viewFile',
                CheckRemotePageController::class => 'index',
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:sysinfo/Resources/Public/Icons/user_mod_m1.svg',
                'labels' => 'LLL:EXT:sysinfo/Resources/Private/Language/locallang_m1.xlf',
            ]
        );
    }
})();
