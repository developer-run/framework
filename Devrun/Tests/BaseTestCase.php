<?php

namespace Devrun\Tests;

use Devrun\ClassNotFoundException;
use Devrun\Migrations\Migration;
use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use Nette\Reflection\AnnotationsParser;
use PHPUnit\Framework\TestCase;
use PHPUnit\Util\Test as TestUtil;

class BaseTestCase extends TestCase {

    use FileTestTrait;

    public static $migrations = true;


    /**
     * implementace Nette inject metodiky pro pohodlnější testy
     *
     * @param string $name
     * @throws \ReflectionException
     */
    private function _injectServices($name = 'inject')
    {
        $reflectClass = new \ReflectionClass(get_called_class());



        foreach ($reflectClass->getProperties() as $property) {
            $res = AnnotationsParser::getAll($property);
            if (isset($res[$name]) ? end($res[$name]) : NULL) {
                $this->injectService($property, $res['var']);
            }
        }
    }


    private function injectService(\ReflectionProperty $property, $resource)
    {
        if (isset($resource[0])) {

            try {
                $service      = $this->getContainer()->getByType($resource[0]);
                $_name        = $property->name;
                $this->$_name = $service;

            } catch (MissingServiceException $exc) {
                die(dump(sprintf('%s [%s] %s - full namespace ?', $exc->getMessage(), __METHOD__, $property->class)));
            }

        }
    }


    /**
     * @return Container
     */
    public static function getContainer()
    {
//        Environment::loadConfig();

//        return Environment::getContext();
        return $GLOBALS['container'];
    }


    /**
     * @param string $neonConfig path
     *
     * @return array
     */
    protected function getProviderFromNeon(string $neonConfig): array
    {
        $this->assertFileExists($data = $neonConfig);

        $neon = new \Nette\Neon\Neon();
        return $neon->decode(file_get_contents($data));
    }


    /**
     * check uri
     *
     * @param string $uri uri
     *
     * @return mixed
     */
    protected function uriCheck($uri)
    {
        return (preg_replace('%^(.*)(\?.*)$%', '$1', $uri));
    }

    /**
     * @throws \Nextras\Migrations\Exception
     */
    public static function setUpBeforeClass(): void
    {
        try {
            $reflectClass = new \ReflectionClass(get_called_class());
            $migrations = $reflectClass->getProperty("migrations")->getValue();

        } catch (\ReflectionException $e) {
            throw new $e;
        }

        if ($migrations) {
            Migration::reset(self::getContainer());
        }

    }



    protected function setUp(): void
    {
        $everyTestNewContainer = false;

        if (!$everyTestNewContainer) {

            $annotations = TestUtil::parseTestMethodAnnotations(
                get_class($this),
                $this->getName()
            );

            $everyTestNewContainer = isset($annotations['method']['return']) || isset($annotations['method']['dataProvider']) || isset($annotations['method']['depends']);
        }

        if ($everyTestNewContainer) {
            /*
             * hack!, if some test methods is depending (previous method return) create new Container
             */
            global /** @var Container $container */
            $container;
            global $_container;

            // $testMethod =$this->getName();
            // fwrite(STDOUT, $testMethod . "\n");

            $container = new $container;

//            $container = Environment::getContext();
//            $container = new $_container;
        }

        try {
            $this->_injectServices();

        } catch (\ReflectionException $e) {
            throw new ClassNotFoundException($e->getMessage());
        }
    }

}

