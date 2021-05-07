<?php

/**
 * run once
 */

class BootstrapTest {

    /** @var string|array appDir or sandbox array */
    private $sandbox;

    /** @var string */
    private $libsDir;

    /** @var array */
    private $robotLoaderDirs;

    /** @var string */
    private $logDir;

    /** @var string */
    private $tempDir;

    /** @var string */
    private $configDir;


    /**
     * @return mixed
     */
    public function getSandbox()
    {
        if (null === $this->sandbox) throw new \Devrun\InvalidArgumentException("setSandbox first");

        return $this->sandbox;
    }

    /**
     * @param mixed $sandbox
     * @return BootstrapTest
     */
    public function setSandbox($sandbox): BootstrapTest
    {
        $this->sandbox = $sandbox;

        if (is_array($sandbox)) $this->setParamsFromArray($sandbox);

        return $this;
    }


    public function setParamsFromArray(array $params): BootstrapTest
    {
        foreach ($params as $dir => $path) {
            if (method_exists($this, $method = "set" . ucfirst($dir))) {
                $this->$method($path);
            }
        }

        return $this;
    }


    /**
     * @return string
     */
    public function getConfigDir(): string
    {
        return $this->configDir;
    }

    /**
     * @param string $configDir
     * @return BootstrapTest
     */
    public function setConfigDir(string $configDir): BootstrapTest
    {
        $this->configDir = $configDir;
        return $this;
    }

    /**
     * @return mixed
     */
    protected function getLibsDir()
    {
        if (null === $this->libsDir) throw new \Devrun\InvalidArgumentException("setVendorDir first");
        if (!is_dir($this->libsDir)) throw new \Devrun\InvalidArgumentException("vendorDir isn't correctly set");
        return $this->libsDir;
    }

    /**
     * @param mixed $libsDir
     * @return BootstrapTest
     */
    public function setLibsDir($libsDir): BootstrapTest
    {
        $this->libsDir = $libsDir;
        return $this;
    }

    public function hasRobotLoaderDirs(): bool
    {
        return null !== $this->robotLoaderDirs;
    }


    /**
     * @return array
     */
    protected function getRobotLoaderDirs(): array
    {
        if (null === $this->robotLoaderDirs) throw new \Devrun\InvalidArgumentException("setRobotLoaderDirs first");
        return $this->robotLoaderDirs;
    }

    /**
     * @param array $robotLoaderDirs
     * @return BootstrapTest
     */
    public function setRobotLoaderDirs(array $robotLoaderDirs): BootstrapTest
    {
        $this->robotLoaderDirs = $robotLoaderDirs;
        return $this;
    }

    /**
     * @return string
     */
    protected function getLogDir(): string
    {
        if (null === $this->logDir) throw new \Devrun\InvalidArgumentException("setLogDirs first");
        if (!is_dir($this->logDir)) throw new \Devrun\InvalidArgumentException("logDir isn't correctly set");
        return $this->logDir;
    }

    /**
     * @param string $logDir
     * @return BootstrapTest
     */
    public function setLogDir(string $logDir): BootstrapTest
    {
        $this->logDir = $logDir;
        return $this;
    }

    /**
     * @return string
     */
    protected function getTempDir(): string
    {
        if (null === $this->tempDir) throw new \Devrun\InvalidArgumentException("setTempDirs first");
        return $this->tempDir;
    }

    /**
     * @param string $tempDir
     * @return BootstrapTest
     */
    public function setTempDir(string $tempDir): BootstrapTest
    {
        if (!is_dir($tempDir)) throw new \Devrun\InvalidArgumentException("tempDir isn't correctly set");
        $this->tempDir = $tempDir;
        return $this;
    }

    /*
     * create and clear logs
     */
    public function createTestLogDir(bool $erase = false): BootstrapTest
    {
        $logDir = $this->getLogDir();
        $logDir .= DIRECTORY_SEPARATOR . 'tests';
        \Nette\Utils\FileSystem::createDir($logDir, 0755);
        if ($erase) \Devrun\Utils\FileTrait::eraseDirFromFiles($logDir, ['*.log', '*.html']);
        $this->sandbox['logDir'] = $logDir;

        return $this;
    }

    public function createTestTempDir(bool $erase = false): BootstrapTest
    {
        $tempDir = $this->getTempDir();
        $tempDir .= DIRECTORY_SEPARATOR . 'tests';
        $tempDir .= DIRECTORY_SEPARATOR . (isset($_SERVER['argv']) ? md5(serialize($_SERVER['argv'])) : getmypid());
        echo(Devrun\Utils\EscapeColors::fg_color("blue", PHP_EOL . "temp) $tempDir" . PHP_EOL));

        \Nette\Utils\FileSystem::createDir($tempDir, 0755);
        if ($erase) \Devrun\Utils\FileTrait::purge($tempDir);
        $this->sandbox['tempDir'] = $tempDir;

        return $this;
    }

    /**
     * @return \Nette\DI\Container
     */
    public function run(): \Nette\DI\Container
    {
        $loader = require $this->getLibsDir() . '/autoload.php';

        $configurator = new \Devrun\Config\Configurator($this->getSandbox(), $debugMode = true, $loader);

        // @todo fix this in next time
        error_reporting(~E_USER_DEPRECATED); // note ~ before E_USER_DEPRECATED

        if ($this->hasRobotLoaderDirs()) {
            $robotLoader = $configurator->createRobotLoader();
            foreach ($this->getRobotLoaderDirs() as $robotLoaderDir) {
                $robotLoader->addDirectory($robotLoaderDir);
            }

            $robotLoader
                ->ignoreDirs += ['templates', 'tests', 'resources'];
            $robotLoader->register();

        }

        $container = $configurator->createContainer();
        return $container;
    }
}

$_test_dir = getcwd();
if ($caller = \Nette\Utils\Strings::before($_test_dir, 'framework')) {
    $caller .= 'framework';

    $sandbox = array(
        'appDir'		=> dirname(__DIR__),
        'baseDir'		=> $baseDir = dirname(dirname(__DIR__)),
        'configDir'		=> $baseDir . '/tests/sandbox/config',
        'dataDir'		=> $baseDir . '/tests/sandbox/data',
        'modulesDir'	=> $baseDir . '/tests/sandbox/modules',
        'migrationsDir'	=> $baseDir . '/tests/sandbox/migrations',
        'libsDir'		=> $baseDir . '/vendor',
        'logDir'		=> $baseDir . '/tests/sandbox/log',
//        'logDir'		=> $baseDir . '/log', // system log dir
        'tempDir'		=> $baseDir . '/tests/sandbox/temp',
        'wwwDir'		=> $baseDir . '/tests/sandbox/www',
        'imageDir'		=> $baseDir . '/tests/sandbox/www/images',
        'wwwCacheDir'	=> $baseDir . '/tests/sandbox/www/cache',
        'publicDir'		=> $baseDir . '/tests/sandbox/www/media',
        'resourcesDir'	=> $baseDir . '/tests/sandbox/resources',
    );

    $container = (new BootstrapTest())
        ->setSandbox($sandbox)
        ->createTestLogDir(true)
        ->createTestTempDir(true)
        ->setRobotLoaderDirs([$sandbox['appDir']])
        ->run();

    unset($caller, $sandbox, $_test_dir);

} elseif ($caller = \Nette\Utils\Strings::before($_test_dir, 'modules')) {
    if (file_exists($sandbox = $caller . "/sandbox.php")) {
        $caller .= 'modules';

        $sandboxParameters = require_once $sandbox;

        $container = (new BootstrapTest())
            ->setSandbox($sandboxParameters)
            ->createTestLogDir(true)
            ->createTestTempDir(false)
            ->setRobotLoaderDirs([$sandboxParameters['modulesDir'] ?? $caller])
            ->run();

        unset($caller, $sandboxParameters, $sandbox);
    }

} else {
    throw new \Devrun\NotSupportedException("unknown test, you must use other bootstrap.");
}

return $_container = $container;
