<?php

namespace Devrun\Tests;

use Devrun\ClassNotFoundException;
use Devrun\Migrations\Migration;
use Nette\DI\Container;
use Nette\DI\MissingServiceException;
use Nette\Environment;
use Nette\Reflection\AnnotationsParser;
use PHPUnit\Framework\DOMTestTrait;
use PHPUnit\Framework\TestCase;

class BaseTestCase extends TestCase {

    use DOMTestTrait;
    use FileTestTrait;

    public static $migrations = false;


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

    public static function setUpBeforeClass()
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



    protected function setUp()
    {
        $annotations = $this->getAnnotations();

        /*
         * hack!, if some test methods is depending (previous method return) create new Container
         */
        if (isset($annotations['method']['return']) ) {
            global $container;
            global $_container;

            // $testMethod =$this->getName();
            // fwrite(STDOUT, $testMethod . "\n");

            $container = Environment::getContext();
            $container = new $_container;
        }

        try {
            $this->_injectServices();

        } catch (\ReflectionException $e) {
            throw new ClassNotFoundException($e->getMessage());
        }
    }

}

