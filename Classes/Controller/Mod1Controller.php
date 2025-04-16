<?php

namespace Taketool\Sysinfo\Controller;

use Closure;
use Doctrine\DBAL\DBALException;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Taketool\Sysinfo\Domain\Repository\LogEntryRepository;
use Taketool\Sysinfo\Service\DeprecationService;
use Taketool\Sysinfo\Service\FlexformService;
use Taketool\Sysinfo\Service\Mod1Service;
use Taketool\Sysinfo\Service\SyslogService;
use Taketool\Sysinfo\Service\UserService;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Exception\Page\PageNotFoundException;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Package\Exception\PackageStatesUnavailableException;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2022-2023 Martin Keller <martin.keller@taketool.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 * ************************************************************* */

/**
 * Module for the 'tool' extension.
 *
 * @author      Martin Keller <martin.keller@taketool.de>
 * @package     Taketool
 * @subpackage  Sysinfo
 */
class Mod1Controller extends ActionController
{
    const EXTKEY = 'sysinfo';

    protected string $publicPath;
    protected string $configPath;
    protected string $extPath;
    protected string $t3version;
    protected bool $isComposerMode = false;
    protected array $globalTemplateVars;

    protected array $hackfiles = [
        'index.php',
        'auto_seo.php',
        'cache.php',
        'wp-blog-header.php',
        'wp-config-sample.php',
        'wp-links-opml.php',
        'wp-login.php',
        'wp-settings.php',
        'wp-trackback.php',
        'wp-activate.php',
        'wp-comments-post.php',
        'wp-cron.php',
        'wp-load.php',
        'wp-mail.php',
        'wp-signup.php',
        'xmlrpc.php',
        'edit-form-advanced.php',
        'link-parse-opml.php',
        'ms-sites.php',
        'options-writing.php',
        'themes.php',
        'admin-ajax.php',
        'edit-form-comment.php',
        'link.php',
        'ms-themes.php',
        'plugin-editor.php',
        'admin-footer.php',
        'edit-link-form.php',
        'load-scripts.php',
        'ms-upgrade-network.php',
        'admin-functions.php',
        'edit.php',
        'load-styles.php',
        'ms-users.php',
        'plugins.php',
        'admin-header.php',
        'edit-tag-form.php',
        'media-new.php',
        'my-sites.php',
        'post-new.php',
        'admin.php',
        'edit-tags.php',
        'media.php',
        'nav-menus.php',
        'post.php',
        'admin-post.php',
        'export.php',
        'media-upload.php',
        'network.php',
        'press-this.php',
        'upload.php',
        'async-upload.php',
        'menu-header.php',
        'options-discussion.php',
        'privacy.php',
        'user-edit.php',
        'menu.php',
        'options-general.php',
        'profile.php',
        'user-new.php',
        'moderation.php',
        'options-head.php',
        'revision.php',
        'users.php',
        'custom-background.php',
        'ms-admin.php',
        'options-media.php',
        'setup-config.php',
        'widgets.php',
        'custom-header.php',
        'ms-delete-site.php',
        'options-permalink.php',
        'term.php',
        'customize.php',
        'link-add.php',
        'ms-edit.php',
        'options.php',
        'edit-comments.php',
        'link-manager.php',
        'ms-options.php',
        'options-reading.php',
        'system_log.php'
    ];

    protected array $falsePositives = [
        '/typo3conf/ext/sysinfo/Classes/Controller/Mod1Controller.php',
    ];
    protected array $fileInfo = [
        '/index.php' => [
            '10.4.37' => ['size' => 987],
            '11.5.36' => ['size' => 822],
            '11.5.37' => ['size' => 822],
            '11.5.38' => ['size' => 822],
            '11.5.39' => ['size' => 822],
            '11.5.40' => ['size' => 822],
            '11.5.41' => ['size' => 822],
        ]
    ];

    protected $backendUserAuthentication;
    protected ConnectionPool $connectionPool;
    protected Environment $environment;
    protected IconFactory $iconFactory;
    protected LogEntryRepository $logEntryRepository;
    protected Mod1Service $mod1Service;
    protected ModuleTemplateFactory $moduleTemplateFactory;
    protected ModuleTemplate $moduleTemplate;
    protected PageRepository $pageRepository;
    protected SiteConfiguration $siteConfiguration;
    protected SyslogService $syslogService;
    protected UserService $userService;
    private \TYPO3\CMS\Core\Core\ApplicationContext $context;
    private string $projectPath;

    public function __construct(
        ConnectionPool $connectionPool,
        Environment $environment,
        IconFactory $iconFactory,
        LogEntryRepository $logEntryRepository,
        ModuleTemplateFactory $moduleTemplateFactory,
        Mod1Service $mod1Service,
        PageRepository $pageRepository,
        SiteConfiguration $siteConfiguration,
        SyslogService $syslogService,
        UserService $userService
    )
    {
        $this->backendUserAuthentication = $GLOBALS['BE_USER'];
        $this->connectionPool = $connectionPool;
        $this->environment = $environment;
        $this->iconFactory = $iconFactory;
        $this->logEntryRepository = $logEntryRepository;
        $this->mod1Service = $mod1Service;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->pageRepository = $pageRepository;
        $this->siteConfiguration = $siteConfiguration;
        $this->syslogService = $syslogService;
        $this->userService = $userService;
    }

    /**
     * initialize action
     * @throws PackageStatesUnavailableException
     */
    public function initializeAction()
    {
        $this->isComposerMode = $this->environment->isComposerMode();
        $this->context = $this->environment->getContext();
        $this->publicPath = $this->environment->getPublicPath();
        $this->projectPath = $this->environment->getProjectPath();
        $this->extPath = $this->environment->getExtensionsPath() . '/' . self::EXTKEY;
        $this->configPath = $this->publicPath . '/typo3conf'; //$environment->getConfigPath();
        $this->t3version = GeneralUtility::makeInstance(Typo3Version::class)->getVersion();
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->mod1Service->addDocHeaderButtons($this->moduleTemplate, $this->uriBuilder);

        // global template information
        $this->globalTemplateVars = [
            't3version' => $this->t3version,
            'context' => $this->context,
            'projectPath' => $this->projectPath,
            'publicPath' => $this->publicPath,
            'typo3Path' => $this->publicPath . '/typo3', // up to T3v11, changes in T3v12
            'isComposerMode' => $this->isComposerMode,
            'memoryLimit' => ini_get('memory_limit'),
            //'sysinfoWebPath' => $sysinfoWebPath,
            //'jsCheckPages' => $jsCheckPages,
        ];
    }

    public function allTemplatesAction(): ResponseInterface
    {
        $templates = $this->getAllTemplates(false);
        $this->templatesToView($templates);
        $this->view->assignMultiple($this->globalTemplateVars);

        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    public function allTemplatesNoCacheAction(): ResponseInterface
    {
        $templates = $this->getAllTemplates(false, true);
        $this->templatesToView($templates);
        $this->view->assignMultiple($this->globalTemplateVars);
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * from all domains gets all robots.txt and sitemap.xml via https:// (might take long!)
     *
     * @return void
     */
    public function checkDomainsAction(): ResponseInterface
    {
        $allDomains = $this->getAllDomains();
        $jsInlineCode = 'var checkFiles = [' . "\n";
        foreach ($allDomains as $domain)
        {
            $jsInlineCode .= '  { site:"' . $domain['site'] . '", url:"' . $domain['baseUrl'] . '"},' . "\n";
        }
        $jsInlineCode .= '];';

        $this->view->assign('allDomains', $allDomains);
        $this->view->assignMultiple($this->globalTemplateVars);

        // add JS
        $pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $pageRenderer->addJsInlineCode('tx_' . self::EXTKEY . '_m1', $jsInlineCode);
        // add checkPages.js is done in template
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    public function deleteFileAction(string $file = ''): ResponseInterface
    {
        $content = 'File could not be deleted';
        if ($file != '') {
            $content = (@unlink($file))
                ? 'File successfully deleted'
                : 'File could not be deleted';
        }
        clearstatcache();

        $this->view->assign('file', $file);
        $this->view->assign('content', $content);
        $this->view->assignMultiple($this->globalTemplateVars);
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    public function deprecationAction(): ResponseInterface
    {
        $arguments = $this->request->getArguments();
        $logFile = (isset($arguments['logFile'])) ? $arguments['logFile'] : '';

        $this->view->assignMultiple([
            'data' => DeprecationService::getLog($logFile),
            'cntFiltered' => count(DeprecationService::getHides()),
            'logFile' => $logFile,
        ]);
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    public function deprecationHideAction(): ForwardResponse
    {
        $arguments = $this->request->getArguments();
        //\nn\t3::debug($arguments);die();
        DeprecationService::hide($arguments['key']);
        return (new ForwardResponse('deprecation'))
            ->withArguments(['logFile' => '']);
    }

    public function deprecationClearHideAction(): ForwardResponse
    {
        DeprecationService::clearHide();
        return (new ForwardResponse('deprecation'))
            ->withArguments(['logFile' => '']);
    }

    public function deprecationDeleteAction(): ForwardResponse
    {
        $arguments = $this->request->getArguments();
        //  \nn\t3::debug($arguments);die();
        $uidList = (isset($arguments['deprecationDeleteButton'])) ? $arguments['deprecationDeleteButton'] : '';
        $logFile = (isset($arguments['logFile'])) ? $arguments['logFile'] : 2;

        if ($uidList == 'DeleteAllDeprecationLogEntries')
        {
            $success = DeprecationService::deleteLog($logFile);
            $this->addFlashMessage(
                $logFile . ' deleted',
                'Deprecations Log',
                AbstractMessage::OK,
                false);
        }
        return (new ForwardResponse('deprecation'))
            ->withArguments(['logFile' => '']);
    }
    public function fileCheckAction(): ResponseInterface
    {
        // part 1: find all files in sys_file and compare to filesystem
        $table = 'sys_file';
        $rows = $this->connectionPool
            ->getConnectionForTable($table)
            ->select(
                ['*'],
                $table
            )->fetchAllAssociative();
        $cntFilesThere= count($rows);
        $filesNotThere = [];
        $sysFile = [];  // cache the database for part 2
        $max = 50;
        foreach($rows as $row)
        {
            //if ($max-- < 1) break;
            $filePath = ($row['storage'] === 1 )
                ? '/fileadmin' . $row['identifier']
                : $row['identifier'];

            // publicPath is something like '/var/www/html/public'
            $isFile = @is_file($this->publicPath . '/' . $filePath);
            if (!$isFile) $filesNotThere[] = $filePath;
            $sysFile[sha1($filePath)] = $filePath;
        }
        $cntFilesNotThere = count($filesNotThere);
        \nn\t3::debug([
            'sysFile' => $sysFile,
            'missing' => $filesNotThere,
        ], 'Files in FAL, but not in filesystem');

        // part 2: excessive files: find all public files and compare to sys_file entries
        $directory = new \RecursiveDirectoryIterator($this->publicPath, \FilesystemIterator::SKIP_DOTS);
        $iterator = new \RecursiveIteratorIterator($directory);
        $excessiveFiles = array();
        foreach ($iterator as $info) {
            // always remove publicPath
            $filePath = str_replace($this->publicPath, '', $info->getPathname());
            if (!array_key_exists(sha1($filePath), $sysFile))
            {
                if (str_contains($filePath, '/fileadmin/kunden/mbauer/panorama/')) continue;
                if (str_contains($filePath, '/Galerie/')) continue;
                if (str_contains($filePath, '/fileadmin/templates/')) continue;
                if (str_contains($filePath, '/_assets/')) continue;
                if (str_contains($filePath, '/_processed_/')) continue;
                if (str_contains($filePath, '/typo3')) continue;
                if (str_contains($filePath, '/index.html')) continue;
                if (str_contains($filePath, '/index.php')) continue;
                if (str_contains($filePath, '/.htaccess')) continue;
                $excessiveFiles[sha1($filePath)] = $filePath;
            }
        }
        $cntExcessiveFiles = count($excessiveFiles);

        \nn\t3::debug([
            '$excessiveFiles' => $excessiveFiles,
        ], 'Files in Filesystem, but not in FAL');

        $this->view->assignMultiple($this->globalTemplateVars);
        $this->view->assignMultiple([
            'cntFilesThere' => $cntFilesThere,
            'cntFilesNotThere' => $cntFilesNotThere,
            'filesNotThere' => $filesNotThere,
            'cntExcessiveFiles' => $cntExcessiveFiles,
            'excessiveFiles' => $excessiveFiles,
        ]);
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    public function flexformAction(): ResponseInterface
    {
        $ff = [];
        $arguments = $this->request->getArguments();
        $cType = (!empty($arguments['cType'])) ? $arguments['cType'] : '';
        $extConf = FlexformService::getConf();

        if (!empty($cType))
        {
            // get all Flexform entries filtered by ffFields
            $ff = FlexformService::getFF($arguments['cType'], $extConf['ffFields']);
        }

        $this->view->assignMultiple($this->globalTemplateVars);
        $this->view->assign('extConf', $extConf);
        $this->view->assign('ffData', $ff);
        $this->view->assign('cType', $cType);

        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    public function indexAction(): ResponseInterface
    {
        $this->view->assignMultiple($this->globalTemplateVars);
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @return void
     */
    public function pluginsAction(): ResponseInterface
    {
        $arguments = $this->request->getArguments();
        $type = (isset($arguments['type']))
            ? $arguments['type']
            : '';
        //DebuggerUtility::var_dump(['$arguments'=>$arguments,'$type'=>$type], __class__.'->'.__function__.'()');

        if ($type == '') {
            $this->view->assign('type', '');
            $this->view->assign('pluginTypes', $this->getAllPluginTypes());
            $this->view->assign('contentTypes', $this->getAllContentTypes());
        } else {
            $this->view->assign('type', $type);
            $this->view->assign('pages4PluginType', $this->getPages4PluginType($type));
            $this->view->assign('pages4ContentType', $this->getPages4ContentType($type));
        }
        $this->view->assignMultiple($this->globalTemplateVars);
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    public function postAction(): ResponseInterface
    {
        $this->view->assignMultiple($this->globalTemplateVars);
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * Feature: Verify at elast for root page that compression and concatination is active
     *
     * @return void
     */
    public function rootTemplatesAction(): ResponseInterface
    {
        /*
        // Check permission to read config tables
        if (!$GLOBALS['BE_USER']->check('tables_select', 'tx_tool_domain_model_config')) {
            $this->addFlashMessage('Berechtigung für diese Seite fehlt.', '', AbstractMessage::ERROR);
            return;
        }
        */
        $arguments = $this->request->getArguments();
        $showAll = (isset($arguments['showAll'])) ? $arguments['showAll'] : '';
        //DebuggerUtility::var_dump(['$arguments'=>$arguments,'showAll'=>$showAll], __class__.'->'.__function__.'()');

        $templates = $this->getAllTemplates();
        $this->templatesToView($templates);
        $this->view->assignMultiple($this->globalTemplateVars);
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    public function securityCheckAction(): ResponseInterface
    {
        // only link to System Reports if Extension is loaded
        $isLoaded = ExtensionManagementUtility::isLoaded('reports');
        $isAdmin = $this->backendUserAuthentication->isAdmin();
        $isSystemReports =  $isLoaded && $isAdmin;

        $localConfPath = $this->configPath . '/LocalConfiguration.php';

        // removed in v9
        // $GLOBALS['TYPO3_CONF_VARS']['BE']['fileExtensions']['webspace']['allow']
        // $GLOBALS['TYPO3_CONF_VARS']['BE']['fileExtensions']['webspace']['deny']

        /*
         * test if $GLOBALS['TYPO3_CONF_VARS']['BE']['fileDenyPattern'] original or altered or empty?
         */
        //$fileDenyPattern = $GLOBALS['TYPO3_CONF_VARS']['BE']['fileDenyPattern'];
        //$fileDenyPatternDefault = '\.(php[3-8]?|phpsh|phtml|pht|phar|shtml|cgi)(\..*)?$|\.pl$|^\.htaccess$';
        //$isFileDenyPatternAltered = $fileDenyPattern != $fileDenyPatternDefault;
        //$isFileDenyPatternEmpty = trim($fileDenyPattern) == '';

        // $this->publicPath/index.php should not be writable: is_writable(string $filename): bool

        // php_errors.log on root
        $phpErrors = $this->publicPath . '/php_errors.log';
        $isPhpErrorsLogOnRoot = @is_file($phpErrors);
        $phpErrorsLogOnRoot = $this->stat($phpErrors);

        /*
         * tests trustedHostsPattern for default/disabled/something
         * $GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern']
         */
        $trustedHostsPattern = $GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'];
        $trustedHostsPattern_disabled = $trustedHostsPattern == '.*';
        $trustedHostsPattern_isDefault = $trustedHostsPattern == 'SERVER_NAME';

        // non composer v9: index.php should be a symlink: is_link()
        // $this->isComposerMode

        /*
         * test index on siteroot
         * needs $this->isComposerMode in template
         * composer: v10: index.php.len should be 987bytes or is assumed altered
         */
        $indexSize = @filesize($this->publicPath . '/index.php');
        $indexSize_shouldBe = $this->fileInfo['/index.php'][$this->t3version]['size'];
        $indexStat = $this->stat($this->publicPath . '/index.php');
        $isIndexSymlink = $indexStat['stat']['nlink'];

        /*
         * test all php on siteroot
         */
        // get all .php files on root
        $phpFiles = [];
        $dir = dir($this->publicPath);
        if ($dir !== false) {
            while (false !== ($entry = $dir->read())) {
                if (substr($entry, -4) == '.php') {
                    $phpFiles[] = $this->stat($this->publicPath . '/' . $entry);
                }
            }
            $dir->close();
        }
        // remove index.php from result set
        $notIndexPhpFiles = $phpFiles;
        $indexKey = array_search('/index.php', array_column($phpFiles, 'short'));
        unset($notIndexPhpFiles[$indexKey]);

        /*
         * test /typo3temp for *.php which should not be there
         */
        // composer: /typo3temp should not contain any .php: find ./|grep .php
        // redirect stderr to stdout using 2>&1 to see error messages as well
        $typo3tempPhps = [];
        $cmd = 'find "' . $this->publicPath . '/typo3temp/" -type "f" -name "*.php" 2>&1';
        exec($cmd, $output, $status);
        foreach ($output as $file) {
            $typo3tempPhps[] = $this->stat($file);
        }
        //\nn\t3::debug($typo3tempPhps);

        /*
         * test /typo3conf, which may contain *.php, for *.php which should not be there
         * use $this->hackfiles to check against
         */
        $typo3confPhps = [];
        $cmd = 'find "' . $this->publicPath . '/typo3conf/" -type "f" -name "*.php" 2>&1';
        exec($cmd, $output, $status);
        foreach ($output as $file) {
            if (in_array(basename($file), $this->hackfiles)) {
                $typo3confPhps[] = $this->stat($file);
            }
        }

        /*
         * test /uploads for php files where no php files should be: uploads (only non composer mode)
         */
        $uploadsPhps = [];
        if (!$this->isComposerMode)
        {
            $cmd = 'find "' . $this->publicPath . '/uploads/" -type "f" -name "*.php" 2>&1';
            exec($cmd, $output, $status);
            foreach ($output as $file) {
                $uploadsPhps[] = $this->stat($file);
            }
            //\nn\t3::debug($uploadsPhps);
        }

        /*
         * test /*.php files and all subdirs which contain suspicious code
         * using basic regular expression
         */
        $searchFor = '';
        $output = '';
        foreach ([
            'error_reporting(0)',
            // too many false-positives'base64_decode(',
            'eval(',
            'gzinflate(',
            'str_rot13(',
        ] as $search) $searchFor .= $search . '\|';
        $searchFor = substr($searchFor, 0, -2);
        $suspiciousPhps = [];
        $cmd = "grep '$searchFor' -rn $this->publicPath --include=*.php  2>&1";
        // status 2 = ERROR
        exec($cmd, $output, $status);
        //\nn\t3::debug([$searchFor,$cmd,$output,$status]);
        foreach ($output as $line) {
            $lineArray= explode(':', $line);
            //\nn\t3::debug($lineArray);
            $file = $lineArray[0];
            if (true) {
                $tmp = [
                    'file' => $this->stat($file),
                    'lnr' => $lineArray[1],
                    'code' => trim($lineArray[2]),
                ];
                // remove from output if false positive
                if (!in_array($tmp['file']['short'], $this->falsePositives)) $suspiciousPhps[] = $tmp;
            }
        }
        //\nn\t3::debug($suspiciousPhps);

        $this->view->assignMultiple([
            /*
            'fileDenyPattern' => $fileDenyPattern,
            'fileDenyPatternDefault' => $fileDenyPatternDefault,
            'isFileDenyPatternEmpty' => $isFileDenyPatternEmpty,
            'isFileDenyPatternAltered' => $isFileDenyPatternAltered,
            */
            'trustedHostsPattern' => $trustedHostsPattern,
            'trustedHostsPattern_disabled' => $trustedHostsPattern_disabled,
            'trustedHostsPattern_isDefault' => $trustedHostsPattern_isDefault,
            'indexSize' => $indexSize,
            'indexSize_shouldBe' => $indexSize_shouldBe,
            'isIndexSymlink' => $isIndexSymlink,
            'indexPhp' => $indexStat,
            'phpErrors' => $phpErrorsLogOnRoot,
            'isPhpErrors' => $isPhpErrorsLogOnRoot,
            'webrootPhps' => $notIndexPhpFiles,
            'isWebrootPhps' => count($notIndexPhpFiles) > 0,
            'typo3tempPhps' => $typo3tempPhps,
            'isTypo3tempPhps' => count($typo3tempPhps) > 0,
            'typo3confPhps' => $typo3confPhps,
            'isTypo3confPhps' => count($typo3confPhps) > 0,
            'uploadsPhps' => $uploadsPhps,
            'isUploadsPhps' => count($uploadsPhps) > 0,
            'suspiciousPhps' => $suspiciousPhps,
            'isSuspiciousPhps' => count($suspiciousPhps) > 0,
            'isSystemReports' => $isSystemReports,
        ]);
        $this->view->assignMultiple($this->globalTemplateVars);
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    public function syslogAction(): ResponseInterface
    {
        $arguments = $this->request->getArguments();
        $logType = (isset($arguments['logType'])) ? $arguments['logType'] : 2;
        $logCategory = ['notices', 'error', 'system error', 'security notices'][$logType];
        $this->view->assignMultiple(
            array_merge(
                [
                    'logType' => $logType,
                    'logCategory' => $logCategory
                ],
                $this->syslogService->getLog($logType)
            )
        );
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @todo only 1st click on delete actually deletes something. Following clicks do nothing!
     * @throws StopActionException
     * @throws DBALException
     */
    public function syslogDeleteAction(): ResponseInterface
    {
        $arguments = $this->request->getArguments();
        //\nn\t3::debug($arguments);//die();
        $uidList = (isset($arguments['uidList'])) ? $arguments['uidList'] : '';
        $logType = (isset($arguments['logType'])) ? $arguments['logType'] : 2;

        if ($uidList == 'DeleteAllThisTypeSysLogEntries')
        {
            $cntDeleted = $this->syslogService->deleteByLogType((int)$logType);
            $this->addFlashMessage(
                $cntDeleted . ' entries of type ' . $logType . ' deleted.',
                'table sys_log',
                AbstractMessage::OK,
                false);
        } else {
            if (!empty($uidList))
            {
                $cntDeleted = $this->syslogService->deleteByUidList($uidList);
                $this->addFlashMessage(
                    $cntDeleted . ' entries deleted.',
                    'table sys_log',
                    AbstractMessage::OK,
                    false);
            }
        }

        return (new ForwardResponse('syslog'))
            ->withArguments(['logType' => $logType]);
    }

    public function userAction(): ResponseInterface
    {
        $groupTree = $this->userService->buildGroupTree();
        $this->view->assignMultiple([
            'tree' => $groupTree,
        ]);
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * @param string $file
     * @return void
     */
    public function viewFileAction(string $file = ''): ResponseInterface
    {
        //\nn\t3::debug($file);

        /* v11
        if ($this->request->hasArgument('file')) {
            $file = $this->request->getArgument('file');
            //\nn\t3::debug($file);die();
        */
        $content = 'Content could not be loaded';
        if ($file != '') {
            $content = @file_get_contents($file);
        }

        $this->view->assign('file', $file);
        $this->view->assign('content', $content);
        $this->view->assignMultiple($this->globalTemplateVars);
        $this->moduleTemplate->setContent($this->view->render());
        return $this->htmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * sort array by certain key, works together with self::sort()
     * @param string $key
     * @param bool $reverse
     * @return Closure
     */
    private static function build_sorter(string $key): Closure
    {
        return function ($a, $b) use ($key) {
            return strnatcmp($a[$key], $b[$key]);
        };
    }
    private static function build_sorter_reverse(string $key): Closure
    {
        return function ($a, $b) use ($key) {
            return strnatcmp($b[$key], $a[$key]);
        };
    }


    /**
     * @return array
     */
    private function getAllContentTypes(): array
    {
        // 1st query: get contentTypes
        $query = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $res = $query->select('*')
            ->from('tt_content')
            ->groupBy('CType')
            ->execute();
        $pT = $res->fetchAll();

        // 2nd query: get count()
        $res = $query->select('*')
            ->from('tt_content')
            ->groupBy('CType')
            ->count('CType')
            ->execute();

        // join the two results into one array
        $i = 0;
        foreach ($res->fetchAll() as $cnt) {
            $pT[$i]['cnt'] = $cnt['COUNT(`CType`)'];
            $i++;
        }
        $contentTypes = [];
        foreach ($pT as $p) {
            if ($p['CType'] == '') continue;
            $contentTypes[] = [
                'CType' => $p['CType'],
                'cnt' => $p['cnt'],
            ];
        }
        //debug(['$query' =>$query, '$res'=>$res, 'pT'=>$pT, '$contentTypes'=>$contentTypes], __line__.':'.__class__.'->'.__function__.'()');
        return $contentTypes;
    }

    /**
     * returns array of rootPid => https://xxx/
     *
     * @return array
     */
    private function getAllDomains($limit = 1000): array
    {
        $domains = $this->siteConfiguration->getAllExistingSites(true);
        //\nn\t3::debug($domains);
        $domainUrls = [];
        foreach ($domains as $domain) {
            //$robotsTxt = @file_get_contents($domain->getConfiguration()['base'] . '/robots.txt');
            //$sitemapXml = @file_get_contents($domain->getConfiguration()['base'] . '/sitemap.xml');
            $domainUrls[$domain->getRootPageId()] = [
                'site' => $domain->getIdentifier(),
                'baseUrl' => $domain->getConfiguration()['base'],
            ];
            if ($limit-- <1) break;
        }
        return $domainUrls;
    }

    /**
     * returns array of rootPid => https://xxx/, isRobotTxt, robotTxt, isSitemapXml, sitemapXml
     * Attention: takes a while in big installations with many domains!
     *
     * @return array
     */
    private function getAllDomainsAndExtra(): array
    {
        $domains = $this->siteConfiguration->getAllExistingSites($useCache = true);
        $domainUrls = [];
        $cnt = 0;
        foreach ($domains as $domain) {

            // we do not need the content, just if the page is readable or not
            $isRobots  = $this->remoteFileExists($domain->getConfiguration()['base'] . 'robots.txt');
            $isSitemap = $this->remoteFileExists($domain->getConfiguration()['base'] . 'sitemap.xml');
            $is404     = $this->remoteFileExists($domain->getConfiguration()['base'] . '404', 'r');

            $domainUrls[$domain->getRootPageId()] = [
                'site' => $domain->getIdentifier(),
                'baseUrl' => $domain->getConfiguration()['base'],
                'isRobotsTxt'  => $isRobots,
                'isSitemapXml' => $isSitemap,
                'isPage404'    => $is404,
            ];

            //if ($cnt++ >10) break;
        }
        return $domainUrls;
    }

    /**
     * @return array
     */
    private function getAllPluginTypes(): array
    {
        // 1st query: get pluginTypes
        $query = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $res = $query->select('*')
            ->from('tt_content')
            ->groupBy('list_type')
            ->execute();
        $pT = $res->fetchAll();

        // 2nd query: get count()
        $res = $query->select('*')
            ->from('tt_content')
            ->groupBy('list_type')
            ->count('list_type')
            ->execute();
        $i = 0;

        // join the two results into one array
        foreach ($res->fetchAll() as $cnt) {
            $pT[$i]['cnt'] = $cnt['COUNT(`list_type`)'];
            $i++;
        }
        $pluginTypes = [];
        foreach ($pT as $p) {
            if ($p['list_type'] == '') continue;
            $pluginTypes[] = [
                'list_type' => $p['list_type'],
                'cnt' => $p['cnt'],
            ];
        }

        //DebuggerUtility::var_dump(['$type'=>$type, '$query' =>$query, '$res'=>$res, 'pT'=>$pT, '$pluginTypes'=>$pluginTypes], __class__.'->'.__function__.'()');
        return $pluginTypes;
    }

    /**
     *
     * @param string $showAll
     * @param bool $filterNoCache
     * @return array
     */
    private function getAllTemplates(bool $rootOnly = true, bool $filterNoCache = false): array
    {
        $query = $this->connectionPool->getQueryBuilderForTable('sys_template');
        if ($rootOnly) {
            $res = $query->select('*')
                ->from('sys_template')
                ->where($query->expr()->eq('root', 1))
                ->execute();
        } else {
            if ($filterNoCache)
            {
                $res = $query->select('*')
                    ->from('sys_template')
                    ->where($query->expr()->like('constants', $query->createNamedParameter('%no_cache%')))
                    ->orWhere($query->expr()->like('config', $query->createNamedParameter('%no_cache%')))
                    ->execute();
            } else {
                $res = $query->select('*')
                    ->from('sys_template')
                    ->execute();
            }

        }
        $templates = $res->fetchAll();
        $pagesOfTemplates = [];
        foreach ($templates as $template) {
            $siteRoot = '- no siteroot -';
            try {
                $rootLineArray = GeneralUtility::makeInstance(RootlineUtility::class, $template['pid'])->get();
                $siteRoot = $rootLineArray[0]['title'];
                unset($rootLineArray[0]);
            } catch (PageNotFoundException $e) {
                // Usually when a page was hidden or disconnected
                // This could be improved by handing in a Context object and decide whether hidden pages
                // Should be linkeable too
                $rootLineArray = [];
            }
            $rLTemp = [];
            foreach ($rootLineArray as $rL) {
                $rLTemp[] = $rL['title'];
            }

            $rootLine = implode('/', array_reverse($rLTemp));
            $pagesOfTemplates[] = [
                'uid' => $template['uid'],
                'pid' => $template['pid'],
                'siteroot' => $siteRoot,
                'rootline' => $rootLine,
                'include_static_file' => $template['include_static_file'],
                'title' => $template['title'],
                'description' => $template['description'],
            ];
        }
        //DebuggerUtility::var_dump(['$query' =>$query, '$res'=>$res, '$templates'=>$templates,'$pagesOfTemplates'=>$pagesOfTemplates], 'getAllTemplates()');
        return $pagesOfTemplates;
    }

    /**
     * @param string
     * @return array
     */
    private function getPages4ContentType($type): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeAll();
        $res = $queryBuilder
            ->select('c.uid', 'c.pid', 'c.hidden as cHidden', 'c.deleted as cDeleted',
                'p.title as pTitle', 'p.hidden as pHidden', 'p.deleted as pDeleted', 'sfr.uid_local')
            ->from('tt_content', 'c')
            ->join(
                'c',
                'pages',
                'p',
                $queryBuilder->expr()->eq('p.uid', $queryBuilder->quoteIdentifier('c.pid'))
            )
            ->join(
                'c',
                'sys_file_reference',
                'sfr',
                $queryBuilder->expr()->eq('sfr.uid_foreign', $queryBuilder->quoteIdentifier('c.uid'))
            )
            ->where($queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter($type)))
            ->executeQuery();
        $plugins = $res->fetchAllAssociative();

        // make results unique again and collect sfr entries to new field
        $p = [];
        foreach ($plugins as $plugin) {
            if (empty($p[$plugin['uid']])) $p[$plugin['uid']] = $plugin;
            $p[$plugin['uid']]['sys_file'][] = $plugin['uid_local'];
            //if ($plugin['uid']==29004) \nn\t3::debug($p[$plugin['uid']]);
        }
        //\nn\t3::debug($p);

        // we need uid and pid and page rootpath and page hidden/deleted info
        $pagesOfContentType = [];
        $rootLineArray = [];
        foreach ($p as $plugin) {
            try {
                $rootLineArray = GeneralUtility::makeInstance(RootlineUtility::class, $plugin['pid'])->get();
            } catch (PageNotFoundException $ex) {
                // Usually when a page was hidden or disconnected
                // This could be improved by handing in a Context object and decide whether hidden pages
                // Should be linkeable too
            }
            $siteRoot = (!empty($rootLineArray[0]['title']))
                ? $rootLineArray[0]['title']
                : '* no title *';

            unset($rootLineArray[0]);
            $rLTemp = [];
            foreach ($rootLineArray as $rL) {
                $rLTemp[] = $rL['title'];
            }

            $rootLine = implode('/', array_reverse($rLTemp));
            $pagesOfContentType[] = [
                'uid' => $plugin['uid'],
                'pid' => $plugin['pid'],
                'cHidden' => $plugin['cHidden'],
                'cDeleted' => $plugin['cDeleted'],
                'pHidden' => $plugin['pHidden'],
                'pDeleted' => $plugin['pDeleted'],
                'siteroot' => $siteRoot,
                'rootline' => $rootLine,
                'sys_file' => $plugin['sys_file'],
            ];
        }

        return $pagesOfContentType;
    }

    /**
     * @param string
     * @return array
     */
    private function getPages4PluginType($type): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()
            ->removeAll();
        $res = $queryBuilder
            ->select('c.uid', 'c.pid', 'c.hidden as cHidden', 'c.deleted as cDeleted',
                'p.title as pTitle', 'p.hidden as pHidden', 'p.deleted as pDeleted')
            ->from('tt_content', 'c')
            ->join(
                'c',
                'pages',
                'p',
                $queryBuilder->expr()->eq('p.uid', $queryBuilder->quoteIdentifier('c.pid'))
            )
            ->where(
                $queryBuilder->expr()->eq('list_type', $queryBuilder->createNamedParameter($type, PDO::PARAM_STR))
            )
            ->execute();
        $plugins = $res->fetchAll();

        /*
        $query = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $res = $query->select('uid', 'pid')
            ->from('tt_content')
            ->where($query->expr()->eq('list_type', $query->createNamedParameter($type)))
            ->execute();
        $plugins = $res->fetchAll();
        */

        // we need uid and pid and page rootpath
        $pagesOfPluginType = [];
        $rootLineArray = [];
        foreach ($plugins as $plugin) {
            try {
                $rootLineArray = GeneralUtility::makeInstance(RootlineUtility::class, $plugin['pid'])->get();
            } catch (PageNotFoundException $e) {
                // Usually when a page was hidden or disconnected
                // This could be improved by handing in a Context object and decide whether hidden pages
                // Should be linkeable too
                $rootLine = [];
            }

            // why can this be empty?
            if (empty($rootLineArray[0])) continue;

            $siteRoot = $rootLineArray[0]['title'];
            unset($rootLineArray[0]);

            $rLTemp = [];
            foreach ($rootLineArray as $rL) {
                $rLTemp[] = $rL['title'];
            }

            $rootLine = implode('/', array_reverse($rLTemp));
            $pagesOfPluginType[] = [
                'uid' => $plugin['uid'],
                'pid' => $plugin['pid'],
                'cHidden' => $plugin['cHidden'],
                'cDeleted' => $plugin['cDeleted'],
                'pHidden' => $plugin['pHidden'],
                'pDeleted' => $plugin['pDeleted'],
                'pTitle' => $plugin['pTitle'],
                'siteroot' => $siteRoot,
                'rootline' => $rootLine,
            ];
        }

        //DebuggerUtility::var_dump(['$type'=>$type, '$pagesOfPluginType'=>$pagesOfPluginType], __class__.'->'.__function__.'()');
        return $pagesOfPluginType;
    }

    /**
     * waits 2 sec for the url to answer
     * and accepts everything HTTP < 400 as success
     *
     * @param $url
     * @return bool
     */
    private function remoteFileExists($url): bool
    {
        //don't fetch the actual page, you only want to check the connection is ok
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 2);
        $result = curl_exec($curl);
        $ret = false;
        if ($result !== false) {
            //if request was ok, check response code
            if (curl_getinfo($curl, CURLINFO_RESPONSE_CODE) < 400) {
                $ret = true;
            }
        }
        curl_close($curl);
        return $ret;
    }

    /**
     * @param array $array
     * @param string $key
     * @param bool $reverse
     * @return array
     */
    private static function sort(array $array, string $key): array
    {
        usort($array, self::build_sorter($key));
        return $array;
    }
    private static function sortReverse(array $array, string $key): array
    {
        usort($array, self::build_sorter_reverse($key));
        return $array;
    }

    /**
     * returns an array of file=>fullpath,stat=stat(fullpath)[uid,gid,mode,size,ctime,mtime]
     *
     * @param string $fullpath
     * @return array
     */
    private function stat(string $fullpath): array
    {
        clearstatcache();
        $stat = @stat($fullpath);

        if (!$stat) {
            return [
                'file' => '[cannot stat file]',
                'stat' => [
                    'uid' => '',
                    'gid' => '',
                    'mode' => '',
                    'nlink' => false,
                    'size' => '',
                    'ctime' => '',
                    'mtime' => '',
                ],
            ];
        } else {
            $posixUserInfo = @posix_getpwuid($stat['uid']);
            $posixGroupInfo = @posix_getgrgid($stat['gid']);
            //\nn\t3::debug($posixUserInfo);
            //\nn\t3::debug($posixGroupInfo);
            return [
                'file' => $fullpath,
                'short' => substr($fullpath, strlen($this->publicPath)),
                'stat' => [
                    'owner' => $posixUserInfo['name'],
                    'group' => $posixGroupInfo['name'],
                    'ow_gr' => $posixUserInfo['name'] . ':' . $posixGroupInfo['name'],
                    'mode' => substr(decoct($stat['mode']), -3, 3),
                    'nlink' => $stat['nlink'] >0,
                    'size' => $stat['size'],
                    'ctime' => $stat['ctime'],
                    'mtime' => $stat['mtime'],
                ],
            ];
        }
    }

    /**
     *
     * @param array $templates
     * @return void
     */
    private function templatesToView(array $templates): void
    {
        foreach ($templates as $key => $t) {
            $config = $this->getTsConfig( $templates[$key]['pid']);

            $templates[$key]['pagetitle'] = $t['rootline'];
            $templates[$key]['include_static_file'] = $t['include_static_file']
                ? implode('<br>', explode(',', $t['include_static_file']))
                : '';
            $templates[$key]['compressCss'] = $config['compressCss'] ?? 'undefined';
            $templates[$key]['compressJs'] = $config['compressJs'] ?? 'undefined';
            $templates[$key]['concatenateCss'] = $config['concatenateCss'] ?? 'undefined';
            $templates[$key]['concatenateJs'] = $config['concatenateJs'] ?? 'undefined';
        }
        // order by siteroot
        $templates = self::sort($templates, 'siteroot');

        $this->view->assign('templates', $templates);
    }

    /**
     * returns config TS settings for specified pid
     *
     * @param $pid
     * @return mixed
     */
    private function getTsConfig($pid)
    {
        $template = GeneralUtility::makeInstance(TemplateService::class);
        $template->tt_track = false;
        $rootline = GeneralUtility::makeInstance(RootlineUtility::class, $pid)->get();
        $template->runThroughTemplates($rootline, 0);
        $template->generateConfig();
        return $template->setup['config.'];
    }

    private function addDocHeaderButtons(): void
    {
        /*  Valid linkButton conditions are:
            trim($this->getHref()) !== ''
            && trim($this->getTitle()) !== ''
            && $this->getType() === self::class
            && $this->getIcon() !== null
        */
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        foreach([
                'syslog' => 'Mod1:Syslog:actions-debug',
                'securityCheck' => 'Mod1:Security Check:module-adminpanel',
                'shaOne' => 'Sha1:Typo3 SHA1:actions-extension',
                'plugins' => 'Mod1:Plugins:content-plugin',
                'rootTemplates' => 'Mod1:Root Templates:actions-template',
                'allTemplates' => 'Mod1:All Templates:actions-template',
                //'noCache' => 'Mod1:no_cache:actions-extension',
                'checkDomains' => 'Mod1:robots.txt, sitemap.xml & 404:install-scan-extensions',
            ] as $action => $param)
        {
            list($controller, $title, $icon) = explode(':', $param);
            $addButton = $buttonBar->makeLinkButton()
                ->setTitle($title)
                ->setShowLabelText($action)
                ->setHref($this->uriBuilder->uriFor($action,null,$controller))
                ->setIcon($this->iconFactory->getIcon($icon, Icon::SIZE_SMALL));
            $buttonBar->addButton($addButton);
        }
        $composerButton = $buttonBar->makeLinkButton()
            ->setTitle(($this->isComposerMode ? 'Composer Mode' : 'Legacy Mode'))
            ->setShowLabelText(($this->isComposerMode ? 'Dieses Typo3 ist eine Composer basierende Installation' : 'Dieses Typo3 ist keine Composer Installation'))
            ->setHref('#')
            ->setIcon($this->iconFactory->getIcon('content-info', Icon::SIZE_SMALL));
        $buttonBar->addButton($composerButton, ButtonBar::BUTTON_POSITION_RIGHT);
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

}
