<?php

require_once dirname(__DIR__) . "/Devrun/Tests/BootstrapTest.php";

return (new BootstrapTest())
    ->setVendorDir(__DIR__ . "/../vendor")
    ->setAppDir(dirname(__DIR__) . '/tests')
    ->run();

die("NOIC");

$loader = require __DIR__ . '/../vendor/autoload.php';

$configurator = new \Devrun\Config\Configurator(dirname(__DIR__) . '/tests', $debugMode = true, $loader);

//error_reporting(~E_USER_DEPRECATED); // note ~ before E_USER_DEPRECATED

$robotLoader = $configurator->createRobotLoader();
$robotLoader
    ->addDirectory(dirname(__DIR__) . "/Devrun")
    ->addDirectory(dirname(__DIR__) . "/Gedmo")
    ->ignoreDirs .= ', templates, test, resources';
$robotLoader->register();

//$environment = $configurator->isDebugMode()
//    ? 'development'
//    : 'production';

//$environment = 'test';

//$configurator->addConfig(__DIR__ . '/sandbox/config/config.neon');
//$configurator->addConfig(__DIR__ . "/sandbox/config/config.$environment.neon");

$container = $configurator->createContainer();
return $container;
