<?php
/**
 * This file is part of the devrun
 * Copyright (c) 2020
 *
 * @file    Configurator.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\Config;

use Composer\Autoload\ClassLoader;
use Devrun;
use Devrun\DI\ImagesExtension;
use Kdyby\Annotations\DI\AnnotationsExtension;
use Kdyby\Console\DI\ConsoleExtension;
use Kdyby\Events\DI\EventsExtension;
use Kdyby\Monolog\DI\MonologExtension;
use Kdyby\Translation\DI\TranslationExtension;
use Nette\DI;
use Nette\DI\Compiler;
use Nette\DI\Container;
use Nette\DI\Helpers;
use Nette\InvalidArgumentException;
use Nette\Loaders\RobotLoader;

/**
 * Class Configurator
 *
 * @package Devrun\Config
 */
class Configurator extends \Nette\Bootstrap\Configurator
{

    /** @var string|array */
    protected $sandbox;

    /** @var Container */
    protected $container;

    /** @var RobotLoader */
    protected $robotLoader;

    /** @var Compiler */
    protected $compiler;

    /** @var ClassLoader|null */
    protected $classLoader;


    /**
     * @param string|array $sandbox
     * @param bool|string|array $debugMode if is null then autodetect will use
     * @param ClassLoader $classLoader
     */
    public function __construct($sandbox, $debugMode = NULL, ClassLoader $classLoader = NULL)
    {
        parent::__construct();
        $this->sandbox     = $sandbox;
        $this->classLoader = $classLoader;
        try {
            umask(0000);

            if ($debugMode) $this->addStaticParameters(['debugMode' => $debugMode]);

            $this->addStaticParameters($this->getSandboxParameters());
            $this->validateConfiguration();
            $this->addStaticParameters($this->getDevrunDefaultParameters($this->staticParameters));
            $this->loadModulesConfiguration();

            $this->enableDebugger($this->staticParameters['logDir']);
            $this->setTempDirectory($this->staticParameters['tempDir']);

            if ($this->classLoader) {
                $this->registerModuleLoaders();
            }
        } catch (InvalidArgumentException $e) {
            die($e->getMessage());
        }

    }

    /**
     * @return array
     * @throws InvalidArgumentException
     */
    public function getSandboxParameters()
    {
        $mandatoryParameters = array('wwwDir', 'appDir', 'baseDir', 'libsDir', 'logDir', 'dataDir', 'tempDir', 'logDir', 'configDir', 'wwwCacheDir', 'publicDir', 'resourcesDir', 'modulesDir', 'migrationsDir');

        if (!is_string($this->sandbox) && !is_array($this->sandbox)) {
            throw new InvalidArgumentException("SandboxDir must be string or array, " . gettype($this->sandbox) . " given.");
        }

        if (is_string($this->sandbox)) {
            if (!file_exists($file = $this->sandbox . '/sandbox.php')) {
                throw new InvalidArgumentException('Sandbox must contain sandbox.php file with path configurations.');
            }
            $parameters = require $file;
        } else {
            $parameters = $this->sandbox;
        }

        foreach ($mandatoryParameters as $item) {
            if (!isset($parameters[$item])) {
                throw new InvalidArgumentException("Sandbox parameters does not contain '{$item}' parameter.");
            }
        }

        foreach ($parameters as $name => $parameter) {
            if (!is_dir($parameter)) {
                throw new InvalidArgumentException("Sandbox parameter '$name' directory does not exist '{$parameter}'");
            }
        }

        return $parameters;
    }


    protected function getDevrunDefaultParameters($parameters = NULL)
    {
        $parameters = (array)$parameters;

        if (!file_exists($settingsFile = $parameters['configDir'] . '/settings.php')) {
            throw new InvalidArgumentException("file $settingsFile not found");
        }

        $settings = require $settingsFile;

        $debugMode = isset($parameters['debugMode']) ?? static::detectDebugMode($settings['debugIPs']);
        $parameters['debugMode'] = $debugMode;

        $ret = array(
            'debugMode' => $debugMode,
            'environment' => ($e = static::detectEnvironment()) ? $e : ($debugMode ? 'development' : 'production'),
            'container' => array(
                'class' => 'SystemContainer',
                'parent' => 'Nette\DI\Container',
            )
        );

        foreach ($settings['modules'] as &$module) {
            $module['path'] = Helpers::expand($module['path'], $parameters);
        }
        $parameters = $settings + $parameters + $ret;
        $parameters['productionMode'] = !$parameters['debugMode'];

        return $parameters;
    }


    public static function detectDebugMode($list = null): bool
    {
        $list = is_string($list)
            ? preg_split('#[,\s]+#', $list)
            : (array) $list;

        $debug = in_array(\Devrun\Utils\Debugger::getIPAddress(), $list) ||
            (PHP_SAPI == 'cli' && \Nette\Utils\Strings::startsWith(getHostByName(getHostName()), "127.0.")) ||
            parent::detectDebugMode($list);

        return $debug;
    }


    public static function detectEnvironment()
    {
        return $_SERVER['SERVER_NAME'] ?? self::getSAPIEnvironment();
    }


    protected static function getSAPIEnvironment()
    {
        $env = [
            'cli'      => 'test',
            'cgi'      => 'cron',
            'cgi-fcgi' => 'cron',
            'fpm-fcgi' => 'cron',
        ];

        return $env[PHP_SAPI] ?? NULL;
    }


    public function setEnvironment($name)
    {
        $this->staticParameters['environment'] = $name;
        return $this;
    }


    /**
     * @return Container
     */
    public function getContainer()
    {
        if (empty($this->container)) {
            $this->container = $this->createContainer();
        }

        return $this->container;
    }


    /**
     * @return Container
     */
    public function createContainer(): DI\Container
    {
        // add config files
        foreach ($this->getConfigFiles() as $file) {
            if (!file_exists($file)) {
                @touch($file);
            }

            $this->addConfig($file);
        }

        // create container
        $container = parent::createContainer();

        // register robotLoader and configurator
        if ($this->robotLoader) {
            $container->addService('robotLoader', $this->robotLoader);
        }
        $container->addService('configurator', $this);

        // intl set default locale
        \Locale::setDefault($container->parameters['lang'] ?? 'cs');

        return $container;
    }



    /**
     * @return array
     */
    protected function getConfigFiles()
    {
        $ret = array();
        $ret[] = $this->staticParameters['configDir'] . '/config.neon';
        $ret[] = $this->staticParameters['configDir'] . "/config_{$this->staticParameters['environment']}.neon";

        if (($agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'admin') == 'admin') {
            $ret[] = $this->staticParameters['configDir'] . "/config_admin.neon";
        }

        return $ret;
    }




    protected function validateConfiguration()
    {
        $mandatoryConfigs = array('settings.php', 'config.neon');

        foreach ($mandatoryConfigs as $config) {
            if (!file_exists($this->staticParameters['configDir'] . '/' . $config)) {
                if (file_exists($origFile = $this->staticParameters['configDir'] . '/' . $config . '.orig')) {
                    if (is_writable($this->staticParameters['configDir']) && file_exists($origFile)) {
                        copy($origFile, $this->staticParameters['configDir'] . '/' . $config);
                    } else {
                        throw new InvalidArgumentException("Config directory is not writable.");
                    }
                } else {
                    throw new InvalidArgumentException("Configuration file '{$config}' does not exist.");
                }
            }
        }
    }


    /**
     * load default module config
     */
    protected function loadModulesConfiguration()
    {
        if (isset($this->staticParameters['modules'])) {
            foreach ($this->staticParameters['modules'] as $items) {
                if ($items['status'] == Devrun\Module\ModuleFacade::STATUS_INSTALLED &&
                    file_exists($fileConfig = $items['path'] . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "config" . DIRECTORY_SEPARATOR . "config.neon")
                ) {
                    $this->addConfig($fileConfig);
                }
            }
        }
    }


    protected function registerModuleLoaders()
    {
        if (isset($this->staticParameters['modules'])) {
            foreach ($this->staticParameters['modules'] as $items) {
                if (isset($items['autoload']['psr-0'])) {
                    foreach ($items['autoload']['psr-0'] as $key => $val) {
                        $this->classLoader->add($key, $items['path'] . '/' . $val);
                    }
                }
                if (isset($items['autoload']['files'])) {
                    foreach ($items['autoload']['files'] as $file) {
                        include_once $items['path'] . '/' . $file;
                    }
                }
            }
        }
    }

    /**
     * @todo check console and annotations extensions
     *
     * @param Compiler $compiler
     */
    public function _generateContainer(DI\Compiler $compiler): void
    {
        $this->onCompile[] = function (Configurator $config, Compiler $compiler) {
            $compiler->addExtension('events', new EventsExtension());
            $compiler->addExtension('console', new ConsoleExtension());
            $compiler->addExtension('annotations', new AnnotationsExtension());
            $compiler->addExtension('translation', new TranslationExtension());
            $compiler->addExtension('monolog', new MonologExtension());
//            $compiler->addExtension('debugger.session', new SessionPanelExtension());

            $compiler->addExtension('core', new Devrun\DI\CoreExtension());
            $compiler->addExtension('imageStorage', new ImagesExtension());
        };

        parent::generateContainer($compiler);
    }


}