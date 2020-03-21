<?php
/**
 * This file is part of the devrun2016
 * Copyright (c) 2017
 *
 * @file    ModuleFacade.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\Module;

use Devrun\Config\Configurator;
use Devrun\InvalidArgumentException;
use Devrun\Module\DependencyResolver\Problem;
use Devrun\Module\DependencyResolver\PublicPagesResult;
use Devrun\Module\DependencyResolver\Solver;
use Devrun\Utils\FileTrait;
use Devrun\Utils\PresenterUtil;
use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Nette\Caching\Storages\FileStorage;
use Nette\DI\Config\Adapters\NeonAdapter;
use Nette\DI\Config\Adapters\PhpAdapter;
use Nette\DI\Container;
use Nette\DI\Helpers;
use Nette\FileNotFoundException;
use Nette\SmartObject;
use Nette\Utils\Finder;
use Nette\Utils\Strings;

/**
 * Class ModuleFacade
 *
 * @package Devrun\Module
 * @method onRegister(ModuleFacade $moduleFacade, $module);
 * @method onUnRegister(ModuleFacade $moduleFacade, $module);
 * @method onInstall(ModuleFacade $moduleFacade, $module);
 * @method onUninstall(ModuleFacade $moduleFacade, $module);
 * @method onUpgrade(ModuleFacade $moduleFacade, $module);
 * @method onUpdate(ModuleFacade $moduleFacade);
 */
class ModuleFacade
{
    use SmartObject;
    use FileTrait;


    const MODULE_CLASS = 'class';

    const MODULE_PATH = 'path';

    const MODULE_STATUS = 'status';

    const MODULE_ACTION = 'action';

    const MODULE_VERSION = 'version';

    const MODULE_AUTOLOAD = 'autoload';

    const MODULE_REQUIRE = 'require';

    const STATUS_UNINSTALLED = 'uninstalled';

    const STATUS_INSTALLED = 'installed';

    const STATUS_UNREGISTERED = 'unregistered';

    const ACTION_INSTALL = 'install';

    const ACTION_UNINSTALL = 'uninstall';

    const ACTION_UPGRADE = 'upgrade';

    const ACTION_NONE = '';

    const COMPOSER_HASH = 'composerHash';

    /** @var array */
    public $onInstall = [];

    /** @var array */
    public $onUninstall = [];

    /** @var array */
    public $onUpgrade = [];

    /** @var array */
    public $onUpdate = [];

    /** @var array */
    public $onRegister = [];

    /** @var array */
    public $onUnRegister = [];

    /** @var array */
    protected static $moduleFiles = array(
        'Module.php',
        '.Devrun.php',
    );

    /** @var array */
    protected static $statuses = array(
        self::STATUS_INSTALLED => 'Installed',
        self::STATUS_UNINSTALLED => 'Uninstalled',
        self::STATUS_UNREGISTERED => 'Unregistered',
    );

    /** @var array */
    protected static $actions = array(
        self::ACTION_NONE => '',
        self::ACTION_INSTALL => 'Install',
        self::ACTION_UNINSTALL => 'Uninstall',
        self::ACTION_UPGRADE => 'Upgrade',
    );

    /** @var IModule[] */
    protected $_findModules;

    /** @var string */
    protected $libsDir;

    /** @var string */
    protected $configDir;

    /** @var string */
    protected $modulesDir;

    /** @var array */
    protected $modules;

    /** @var IModule[] */
    protected $_modules;

    /** @var int */
    protected $_systemContainer = 1;

    /** @var array */
    private $modulesPath = array();

    /** @var Container */
    protected $context;

    /** @var IStorage */
    private $pageStorage;

    /** @var Cache */
    private $pageCache;

    /** @var string */
    private $pageStorageExpiration = '30 minutes';


    /**
     * ModuleFacade constructor.
     *
     * @param IStorage|FileStorage $pageStorage
     * @param Container            $container
     */
    public function __construct(IStorage $pageStorage, Container $container)
    {
        $this->pageStorage = $pageStorage;
        $this->pageCache   = new Cache($pageStorage, 'pages');
        $this->context     = $container;


        $this->libsDir    = $container->parameters['libsDir'];
        $this->modulesDir = $container->parameters['modulesDir'];
        $this->configDir  = $container->parameters['configDir'];

        $this->reloadInfo();
    }

    /**
     * DI setter
     *
     * @param string $pageStorageExpiration
     */
    public function setPageStorageExpiration($pageStorageExpiration)
    {
        $this->pageStorageExpiration = $pageStorageExpiration;
    }


    /**
     * Reload info.
     */
    protected function reloadInfo()
    {
        $data = $this->loadModuleConfig();
        $this->modules = $data['modules'];
        $this->_findModules = NULL;
        $this->_modules = NULL;
    }


    /**
     * Reload system container.
     * @todo not implemented yet
     */
    protected function reloadSystemContainer()
    {
        return;


        /** @var $configurator Configurator */
        $configurator = $this->context->configurator;
        $class = $this->context->parameters['container']['class'] . $this->_systemContainer++;
        LimitedScope::evaluate($configurator->buildContainer($dependencies, $class));

        /** @var context Container */
        $this->context = new $class;
        $this->context->parameters = (include $this->configDir . '/settings.php') + $this->context->parameters;
        $this->context->initialize();
        $this->context->addService("configurator", $configurator);
    }

    /**
     * @return Container
     */
    public function getContext(): Container
    {
        return $this->context;
    }


    /**
     * DI setter
     *
     * @param array $modulesPath
     */
    public function setModulesPath(array $modulesPath)
    {
        $this->modulesPath = $modulesPath;
    }


    /**
     * @param $file
     *
     * @return string
     * @throws InvalidArgumentException
     */
    protected function getModuleClassByFile($file)
    {
        return $this->getClassByFile($file);
    }


    /**
     * Get module status
     *
     * @param IModule $module
     * @return string
     */
    public function getStatus(IModule $module)
    {
        if (!isset($this->modules[$module->getName()])) {
            return self::STATUS_UNREGISTERED;
        }

        return $this->modules[$module->getName()][self::MODULE_STATUS];
    }

    /**
     * Set module status
     *
     * @param IModule $module
     * @param $status
     * @throws InvalidArgumentException
     */
    public function setStatus(IModule $module, $status)
    {
        if (!isset(self::$statuses[$status])) {
            throw new InvalidArgumentException("Status '{$status}' not exists.");
        }

        if ($status === self::STATUS_UNREGISTERED) {
            throw new InvalidArgumentException("Cannot set status '{$status}'.");
        }

        $modules = $this->loadModuleConfig();
        $modules['modules'][$module->getName()][self::MODULE_STATUS] = $status;
        $this->saveModuleConfig($modules);
    }


    /**
     * Get module action
     *
     * @param IModule $module
     * @return string
     */
    public function getAction(IModule $module)
    {
        return $this->modules[$module->getName()][self::MODULE_ACTION];
    }

    /**
     * Set module action
     *
     * @param IModule $module
     * @param         $action
     */
    public function setAction(IModule $module, $action)
    {
        if (!isset(self::$actions[$action])) {
            throw new InvalidArgumentException("Action '{$action}' not exists");
        }

        $modules = $this->loadModuleConfig();
        $modules['modules'][$module->getName()][self::MODULE_ACTION] = $action;
        $this->saveModuleConfig($modules);
    }


    /**
     * @param $action
     * @param IModule $module
     * @param bool $withDependencies
     * @return mixed
     */
    public function doAction($action, IModule $module, $withDependencies = FALSE)
    {
        return $this->{$action}($module, $withDependencies);
    }


    /**
     * Do all actions
     *
     * @throws \Exception
     */
    public function update()
    {
        // unregister
        foreach ($this->getModulesForUnregister() as $name => $args) {
            $this->unregister($name);
        }

        // register
        foreach ($this->getModulesForRegister() as $module) {
            $this->register($module);
        }

        // uninstall
        foreach ($this->getModulesForUninstall() as $module) {
            $this->uninstall($module);
        }

        // upgrade
        foreach ($this->getModulesForUpgrade() as $module) {
            $this->upgrade($module);
        }

        // install
        foreach ($this->getModulesForInstall() as $module) {
            $this->install($module);
        }

        $this->reloadInfo();
        $this->onUpdate($this);
    }


    public function create(IModule $module)
    {
        $config = $this->loadAppConfig();
        // ...?

    }


    public function delete(IModule $module)
    {

    }



    /**
     * Registration of module.
     *
     * @param IModule $module
     */
    public function register(IModule $module)
    {
        if ($this->getStatus($module) !== self::STATUS_UNREGISTERED) {
            throw new InvalidArgumentException("Module '{$module->getName()}' is already registered");
        }

        $modules = $this->loadModuleConfig();

        if (!array_search($module->getName(), $modules['modules'])) {
            $modules['modules'][$module->getName()] = array(
                self::MODULE_STATUS => self::STATUS_UNINSTALLED,
                self::MODULE_ACTION => self::ACTION_NONE,
                self::MODULE_CLASS => $module->getClassName(),
                self::MODULE_VERSION => $module->getVersion(),
                self::MODULE_PATH => $this->getFormattedPath($module->getPath()),
                self::MODULE_AUTOLOAD => $this->getFormattedPath($module->getAutoload()),
                self::MODULE_REQUIRE => $module->getRequire(),
            );
        }

        $this->saveModuleConfig($modules);

        $this->reloadInfo();
        $this->onRegister($this, $module);
    }


    /**
     * Unregistration of module.
     *
     * @param $name
     */
    public function unRegister($name)
    {
        if (!isset($this->modules[$name])) {
            throw new InvalidArgumentException("Module '{$name}' is already unregistered");
        }

        $modules = $this->loadModuleConfig();

        unset($modules['modules'][$name]);
        $this->saveModuleConfig($modules);

        $this->reloadInfo();
        $this->onUnRegister($this, $name);
    }


    /**
     * Installation of module.
     *
     * @param IModule $module
     * @param bool    $force
     *
     * @throws \Exception
     */
    public function install(IModule $module, $force = FALSE)
    {
        if ($this->getStatus($module) === self::STATUS_INSTALLED) {
            throw new InvalidArgumentException("Module '{$module->getName()}' is already installed");
        }

        if (!$force) {
            $dependencyResolver = $this->createSolver();
            $dependencyResolver->testInstall($module);
        }

        foreach ($module->getInstallers() as $class => $args) {

            if (!is_array($args)) {
                $class = $args;
                $args  = [];
            }

            $this->reloadSystemContainer();

            try {
                $installer = $this->context->createInstance($class, $args);
                $installer->install($module);

            } catch (\Exception $e) {
                foreach ($module->getInstallers() as $class2 => $args2) {
                    if ($class === $class2) {
                        break;
                    }

                    if (!is_array($args2)) {
                        $class2 = $args2;
                        $args2  = [];
                    }

                    $installer = $this->context->createInstance($class2, $args2);
                    $installer->uninstall($module);
                }

                throw $e;
            }
        }

        $modules = $this->loadModuleConfig();
        $modules['modules'][$module->getName()] = array(
            self::MODULE_STATUS => self::STATUS_INSTALLED,
            self::MODULE_ACTION => self::ACTION_NONE,
            self::MODULE_CLASS => $module->getClassName(),
            self::MODULE_VERSION => $module->getVersion(),
            self::MODULE_PATH => $this->getFormattedPath($module->getPath()),
            self::MODULE_AUTOLOAD => $this->getFormattedPath($module->getAutoload()),
            self::MODULE_REQUIRE => $module->getRequire(),
        );
        $this->saveModuleConfig($modules);
        $this->reloadInfo();
        $this->reloadSystemContainer();
//        $this->cacheManager->clean();
        $this->onInstall($this, $module);
    }


    /**
     * Uninstallation of module.
     *
     * @param IModule $module
     * @param bool    $force
     *
     * @throws \Exception
     */
    public function uninstall(IModule $module, $force = FALSE)
    {
        if ($this->getStatus($module) === self::STATUS_UNINSTALLED) {
            throw new InvalidArgumentException("Module '{$module->getName()}' is already uninstalled");
        }

        if (!$force) {
            $dependencyResolver = $this->createSolver();
            $dependencyResolver->testUninstall($module);
        }

        foreach ($module->getInstallers() as $class) {

             $this->reloadSystemContainer();

            try {
                $installer = $this->context->createInstance($class);
                $installer->uninstall($module);
            } catch (\Exception $e) {
                foreach ($module->getInstallers() as $class2) {
                    if ($class === $class2) {
                        break;
                    }

                    $installer = $this->context->createInstance($class2);
                    $installer->install($module);
                }

                throw $e;
            }
        }

        $this->setAction($module, self::ACTION_NONE);
        $this->setStatus($module, self::STATUS_UNINSTALLED);
        $this->reloadInfo();
        $this->reloadSystemContainer();

//        $this->cacheManager->clean();
        $this->onUninstall($this, $module);
    }


    /**
     * Upgrade module.
     *
     * @param IModule $module
     * @param bool    $force
     *
     * @throws \Exception
     */
    public function upgrade(IModule $module, $force = FALSE)
    {
        if ($this->getStatus($module) !== self::STATUS_INSTALLED) {
            throw new InvalidArgumentException("Module '{$module->getName()}' must be installed");
        }

        $modules = $this->loadModuleConfig();
        if ($module->getVersion() === $modules['modules'][$module->getName()][self::MODULE_VERSION]) {
            throw new InvalidArgumentException("Module '{$module->getName()}' is current");
        }

        if (!$force) {
            $dependencyResolver = $this->createSolver();
            $dependencyResolver->testUpgrade($module);
        }

        foreach ($module->getInstallers() as $class) {
            try {
                /** @var $installer IInstaller */
                $installer = $this->context->createInstance($class);
                $installer->upgrade($module, $this->modules[$module->getName()][self::MODULE_VERSION], $module->getVersion());

            } catch (\Exception $e) {
                foreach ($module->getInstallers() as $class2) {
                    if ($class === $class2) {
                        break;
                    }

                    $installer = $this->context->createInstance($class2);
                    $installer->downgrade($module, $module->getVersion(), $this->modules[$module->getName()][self::MODULE_VERSION]);
                }

                throw $e;
            }
        }

        $modules['modules'][$module->getName()] = array(
            self::MODULE_STATUS => self::STATUS_INSTALLED,
            self::MODULE_ACTION => self::ACTION_NONE,
            self::MODULE_CLASS => $module->getClassName(),
            self::MODULE_VERSION => $module->getVersion(),
            self::MODULE_PATH => $this->getFormattedPath($module->getPath()),
            self::MODULE_AUTOLOAD => $this->getFormattedPath($module->getAutoload()),
            self::MODULE_REQUIRE => $module->getRequire(),
        );

        $this->saveModuleConfig($modules);
        $this->reloadInfo();
         $this->reloadSystemContainer();
        //$this->cacheManager->clean();
        $this->onUpgrade($this, $module);
    }






    /**
     * @param IModule $module
     * @return DependencyResolver\Problem
     */
    public function testInstall(IModule $module)
    {
        $problem = new Problem();
        $dependencyResolver = $this->createSolver();
        $dependencyResolver->testInstall($module, $problem);
        return $problem;
    }

    /**
     * @param IModule $module
     * @return DependencyResolver\Problem
     */
    public function testUninstall(IModule $module)
    {
        $problem = new Problem;
        $dependencyResolver = $this->createSolver();
        $dependencyResolver->testUninstall($module, $problem);
        return $problem;
    }

    /**
     * @param IModule $module
     * @return DependencyResolver\Problem
     */
    public function testUpgrade(IModule $module)
    {
        $problem = new Problem;
        $dependencyResolver = $this->createSolver();
        $dependencyResolver->testUpgrade($module, $problem);
        return $problem;
    }





    /**
     * Create instance of module.
     *
     * @param $name
     * @return IModule
     */
    public function createInstance($name)
    {
        if (isset($this->modules[$name])) {
            $class = $this->modules[$name][self::MODULE_CLASS];
            if (!class_exists($class)) {
                $path = Helpers::expand($this->modules[$name][self::MODULE_PATH], $this->context->getParameters());
                require_once $path . '/Module.php';
            }
            return new $class;
        }

        $modules = $this->findModules();
        if (isset($modules[$name])) {
            return $modules[$name];
        }

        throw new InvalidArgumentException("Module '{$name}' does not exist.");
    }


    /**
     * @return array
     */
    public function loadModuleConfig()
    {
        $config = new PhpAdapter();
        return $config->load($this->getModuleConfigPath());
    }


    protected function loadAppConfig()
    {
        $config = new NeonAdapter();
        return $config->load($this->getAppConfigPath());
    }


    /**
     * @param $data
     */
    public function saveModuleConfig($data)
    {
        $config = new PhpAdapter;

        if (!is_writable($this->getModuleConfigPath())) throw new FileNotFoundException("file {$this->getModuleConfigPath()} not writable");
        file_put_contents($this->getModuleConfigPath(), $config->dump($data));
    }


    protected function saveAppConfig($data)
    {
        $config = new NeonAdapter;

        if (!is_writable($this->getAppConfigPath())) throw new FileNotFoundException("file {$this->getAppConfigPath()} not writable");
        file_put_contents($this->getAppConfigPath(), $config->dump($data));
    }


    /**
     * @return string
     */
    protected function getModuleConfigPath()
    {
        return $this->configDir . '/settings.php';
    }


    protected function getAppConfigPath()
    {
        return $this->configDir . '/config.neon';
    }



    /**
     * @param \SplFileInfo $file
     */
    private function findModulesClosure(\SplFileInfo $file)
    {
        $class  = $this->getModuleClassByFile($file->getPathname());
        $module = $this->createInstanceOfModule($class, dirname($file->getPathname()));

        $this->_findModules[$module->getName()] = $module;
    }

    /**
     * @param $class
     * @param $path
     *
     * @return IModule
     */
    protected function createInstanceOfModule($class, $path)
    {
        if (!class_exists($class)) {
            require_once $path . DIRECTORY_SEPARATOR . self::$moduleFiles[0];
        }
        return new $class;
    }


    /**
     * @return IModule[]
     */
    public function findModules()
    {
        if ($this->_findModules === NULL) {
            $this->_findModules = array();

            foreach (Finder::findDirectories('*')->in([$this->modulesDir, $this->libsDir . "/devrun"])->limitDepth(0) as $dir) {
                foreach (self::$moduleFiles as $moduleFile) {
                    foreach (Finder::findFiles($moduleFile)->in($dir) as $file) {
                        $this->findModulesClosure($file);
                    }
                }
            }
        }

        return $this->_findModules;
    }


    /**
     * @return IModule[]
     */
    public function getModules()
    {
        if ($this->_modules === NULL) {
            $this->_modules = array();

            foreach ($this->modulesPath as $module => $info) {

                if (is_dir($info['path'])) {
                    foreach (self::$moduleFiles as $moduleFile) {
                        if (file_exists($classFile = $info['path'] . "/$moduleFile")) {
                            $class                   = $this->getModuleClassByFile($classFile);
                            $this->_modules[$module] = $this->createInstanceOfModule($class, $info['path']);
                        }
                    }
                }
            }
        }

        return $this->_modules;
    }


    /**
     * @return IModule[]
     */
    protected function getModulesForRegister()
    {
        $activeModules = $this->getModules();
        $modules = $this->findModules();
        $diff = array_diff(array_keys($modules), array_keys($activeModules));

        $ret = array();
        foreach ($diff as $name) {
            $ret[$name] = $modules[$name];
        }

        return $ret;
    }


    /**
     * @return array
     */
    protected function getModulesForUnregister()
    {
        $ret = array();
        foreach ($this->modules as $name => $args) {
            $path = Helpers::expand($args[self::MODULE_PATH], $this->context->getParameters());
            if (!file_exists($path)) {
                $ret[$name] = $args;
            }
        }

        return $ret;
    }


    /**
     * @return IModule[]
     */
    protected function getModulesForInstall()
    {
        return $this->getModulesByAction(self::ACTION_INSTALL);
    }


    /**
     * @return IModule[]
     */
    protected function getModulesForUninstall()
    {
        return $this->getModulesByAction(self::ACTION_UNINSTALL);
    }


    /**
     * @return IModule[]
     */
    protected function getModulesForUpgrade()
    {
        return $this->getModulesByAction(self::ACTION_UPGRADE);
    }







    /**
     * Get modules by action.
     *
     * @param $action
     * @return IModule[]
     * @throws InvalidArgumentException
     */
    protected function getModulesByAction($action)
    {
        if (!isset(self::$actions[$action])) {
            throw new InvalidArgumentException("Action '{$action}' not exists");
        }

        $ret = array();
        foreach ($this->findModules() as $name => $module) {
            if ($this->getAction($module) === $action) {
                $ret[$name] = $module;
            }
        }

        return $ret;
    }


    /**
     * Get modules by status.
     *
     * @param $status
     *
     * @return IModule[]
     */
    protected function getModulesByStatus($status)
    {
        if (!isset(self::$statuses[$status])) {
            throw new InvalidArgumentException("Status '{$status}' not exists.");
        }

        $ret = array();
        foreach ($this->findModules() as $name => $module) {
            if ($this->getStatus($module) === $status) {
                $ret[$name] = $module;
            }
        }

        return $ret;
    }


    /**
     * find public static pages in all modules which is set to public
     *
     * @param bool $need
     *
     * @return array [module][presenter][page]
     */
    public function findUnSyncedPublicStaticPages(bool $need = false)
    {
        $pages = [];
        if ($need || !$this->getLastSyncedPublicPages()) {

            $files = [];
            $scanTimeDirectories = [];

            if ($modules = $this->getModules()) {

                foreach ($modules as $module => $moduleInfo) {
                    if ($moduleInfo->hasPublishedPages()) {

                        $paths            = [];
                        $publicPresenters = $this->context->findByTag(PresenterUtil::PUBLIC_PRESENTER_TAG);

                        /** @var \SplFileInfo $presenter */
                        foreach (Finder::findDirectories("presenters")->from($moduleInfo->getPath()) as $presenter) {
                            $paths[] = $presenter->getPathname();
                        };

                        /** @var \SplFileInfo $dir */
                        foreach (Finder::findDirectories()->from($paths) as $dir) {
                            $scanTimeDirectories[$dir->getPathname()] = $dir->getMTime();
                        }

                        /** @var \SplFileInfo $layout */
                        foreach (Finder::findFiles("@layout*")->from($paths) as $layout) {
                            $files[] = $layout->getPathname();
                        };


                        foreach ($paths as $path) {

                            /** @var \SplFileInfo $presenter */
                            foreach (Finder::findFiles("*Presenter.php")->exclude('Abstract*', "Error*")->from($path) as $presenter) {
                                $presenterName = Strings::before(basename($presenter), 'Presenter');
                                $files[]       = $presenter->getPathname();

                                $presenterClassName   = $this->getClassByFile($presenter->getPathname());
                                $presenterServiceName = PresenterUtil::getServiceNameFromClassName($presenterClassName);
                                $hasAnnotationPublic  = (new \Nette\Reflection\ClassType($presenterClassName))->hasAnnotation('public');

                                // scan only if scanned presenter is in public list and exist directory for this presenter
                                if ((isset($publicPresenters[$presenterServiceName]) || $hasAnnotationPublic) && is_dir($presenterTemplate = "$path/templates/$presenterName")) {

                                    /** @var \SplFileInfo $template */
                                    foreach (Finder::findFiles("*.latte")->exclude('@layout*')->from($presenterTemplate) as $template) {

                                        $files[]    = $template->getPathname();
                                        $actionName = Strings::before(basename($template), '.latte');

                                        $pages[$module][$presenterName][$actionName] = [
                                            'class'            => $presenterClassName,
                                            'service'          => $presenterServiceName,
                                            'realClassPath'    => $presenter->getPathname(),
                                            'template'         => Strings::after($template->getRealPath(), 'src' . DIRECTORY_SEPARATOR),
                                            'realTemplatePath' => $template->getRealPath(),
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $result = (new PublicPagesResult())
                ->setPageTimes($pages)
                ->setScanDirectories($scanTimeDirectories);

            $this->pageCache->save('pages', $result, [
                Cache::EXPIRE => $this->pageStorageExpiration,
                Cache::FILES  => $files,
            ]);

        }

        return $pages;
    }


    /**
     * page last sync pages in cache, return null if any is expired
     *
     * @return array|null
     */
    private function getLastSyncedPublicPages()
    {
        /** @var PublicPagesResult $cachePages */
        if ($cachePages = $this->pageCache->load('pages')) {
            $pages = $cachePages->getScanDirectories();

            /** @var \SplFileInfo $dir */
            foreach (Finder::findDirectories()->from(array_keys($pages)) as $index => $dir) {
                if ($dir->getMTime() != $pages[$index]) {
                    return null; // one directory last time modify not correct
                }
            };

            return $cachePages->getPages();
        }

        return null;
    }


    /**
     * @return Solver
     */
    protected function createSolver()
    {
        $config = $this->loadModuleConfig();
        return new Solver($this->getModules(), $this->getModulesByStatus(self::STATUS_INSTALLED), $config['modules'], $this->libsDir, $this->modulesDir);
    }


    /**
     * @param $path
     *
     * @return mixed
     */
    private function getFormattedPath($path)
    {
        $path = str_replace('\\', '/', $path);
        $libsDir = str_replace('\\', '/', $this->libsDir);
        $modulesDir = str_replace('\\', '/', $this->modulesDir);

        $tr = array(
            $libsDir => '%libsDir%',
            $modulesDir => '%modulesDir%',
        );

        return str_replace(array_keys($tr), array_merge($tr), $path);
    }

}