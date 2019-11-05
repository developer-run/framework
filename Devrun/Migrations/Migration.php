<?php

namespace Devrun\Migrations;

use Devrun\FileNotFoundException;
use Devrun\Utils\EscapeColors;

class Migration
{

    protected static $autoCreateNonExistDir = true;


    /**
     * run all migrations mode reset
     * useful for testing
     *
     * @param \Nette\DI\Container $container
     * @throws \Nextras\Migrations\Exception
     */
    public static function reset(\Nette\DI\Container $container)
    {
        /** @var \Kdyby\Doctrine\EntityManager $em */
        $em     = $container->getByType('Kdyby\Doctrine\EntityManager');
        $conn   = $em->getConnection();
        $tmpDir = $container->parameters['tempDir'];

        if (file_exists($dbSnapshot = "$tmpDir/db-snapshot.sql")) {
            try {
                echo(EscapeColors::fg_color("cyan", PHP_EOL . "init database from snapshot..." . PHP_EOL));

            } catch (\Exception $e) {
            }
            \Kdyby\Doctrine\Helpers::loadFromFile($conn, $dbSnapshot);

        } else {
            $controller = self::init($container, $conn);
            $controller->run($action = 'run', $groups = ['structures', 'basic-data', 'dummy-data'], \Nextras\Migrations\Engine\Runner::MODE_RESET);

            $username = $container->parameters['database']['user'];
            $password = $container->parameters['database']['password'];
            $dbname = $container->parameters['database']['dbname'];

            try {
                echo(EscapeColors::fg_color("cyan", PHP_EOL . "make dump of generated migration..." . PHP_EOL));

            } catch (\Exception $e) {
            }

            $command = "mysqldump -u $username -p$password $dbname > $dbSnapshot";
            shell_exec($command);
        }


    }


    /**
     * run continue
     * useful for update
     *
     * @param \Nette\DI\Container $container
     * @throws \Nextras\Migrations\Exception
     */
    public static function continue(\Nette\DI\Container $container)
    {
        /** @var \Kdyby\Doctrine\EntityManager $em */
        $em = $container->getByType('Kdyby\Doctrine\EntityManager');
        $conn = $em->getConnection();

        $controller = self::init($container, $conn);
        $controller->run($action = 'run', $groups = ['structures', 'basic-data', 'production'], \Nextras\Migrations\Engine\Runner::MODE_CONTINUE);
    }



    /**
     * @param \Nette\DI\Container $container
     * @param $conn
     *
     * @return Controllers\ExecController
     */
    private static function init(\Nette\DI\Container $container, $conn)
    {
        $dbal = new \Nextras\Migrations\Bridges\DoctrineDbal\DoctrineAdapter($conn);
        $driver = new \Nextras\Migrations\Drivers\MySqlDriver($dbal);
        $controller = new \Devrun\Migrations\Controllers\ExecController($driver);

        self::check($baseDir = $container->parameters['migrationsDir']);

        $controller->addGroup('structures', "$baseDir/structures");
        $controller->addGroup('basic-data', "$baseDir/basic-data", array('structures'));
        $controller->addGroup('dummy-data', "$baseDir/dummy-data", array('basic-data'));
        $controller->addGroup('production', "$baseDir/production", array('basic-data'));
        $controller->addExtension('sql', new \Nextras\Migrations\Extensions\SqlHandler($driver));
        $controller->addExtension('php', new \Nextras\Migrations\Extensions\PhpHandler(['container' => $container]));

        return $controller;
    }


    /**
     * @param \Nette\DI\Container $container
     * @throws \Doctrine\DBAL\DBALException
     * @deprecated use initMigrations instead
     */
    public static function initDatabase(\Nette\DI\Container $container)
    {
        /** @var \Kdyby\Doctrine\EntityManager $em */
        $em = $container->getByType('Kdyby\Doctrine\EntityManager');
        $conn = $em->getConnection();
//        dump($appDir = $container->getParameters()['appDir']);

        $conn->prepare("SET FOREIGN_KEY_CHECKS = 0")->execute();
//        $conn->prepare("TRUNCATE TABLE emails")->execute();
//        $conn->prepare("TRUNCATE TABLE log")->execute();
        $conn->prepare("SET FOREIGN_KEY_CHECKS = 1")->execute();

        if (file_exists($dumpSql = __DIR__ . '/dump.sql')) {
            if (self::$loadDump) {
                \Kdyby\Doctrine\Helpers::loadFromFile($conn, $dumpSql);

            } else {
//                EscapeColors::all_bg();
//                EscapeColors::all_fg();
                echo(EscapeColors::bg_color("magenta", PHP_EOL . strtoupper("--- load `dump.sql` is off ---") . PHP_EOL . PHP_EOL));
            }

        }


    }


    /**
     * @param $baseDir
     */
    private static function check(string $baseDir)
    {
        foreach (['structures', 'basic-data', 'dummy-data', 'production',] as $dir) {
            if (!is_dir($dirName = "$baseDir/$dir")) {
                if (self::$autoCreateNonExistDir) @mkdir($dirName);
            }
            if (!is_dir($dirName = "$baseDir/$dir")) {
                throw new FileNotFoundException("Migration directory $dirName not found!");
            }
        }
    }


}