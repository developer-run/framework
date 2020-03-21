<?php
/**
 * This file is part of the devrun
 * Copyright (c) 2016
 *
 * @file    CompilerExtension.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\Config;

use Devrun;
use Nette;
use Tracy\Debugger;

class CompilerExtension extends \Nette\DI\CompilerExtension
{

    const TAG_NETTE_PRESENTER  = 'nette.presenter';
    const TAG_DEVRUN_PRESENTER = 'devrun.presenter';


    public function loadConfiguration()
    {
        parent::loadConfiguration();

        $builder = $this->getContainerBuilder();

        $fileName         = Nette\Reflection\ClassType::from($this)->fileName;
        $relativeFileName = Nette\Utils\Strings::after($fileName, $builder->parameters['baseDir']);
        $relativeFileName = ltrim($relativeFileName, DIRECTORY_SEPARATOR);

        $modulePath = strpos($fileName, 'src')
            ? Nette\Utils\Strings::before($fileName, 'src')
            : dirname(dirname(dirname(dirname(dirname($fileName)))));

        $moduleRelativePath = strpos($fileName, 'src')
            ? Nette\Utils\Strings::before($relativeFileName, 'src')
            : dirname(dirname(dirname(dirname(dirname($relativeFileName)))));

        $builder->parameters['modules'][$this->name]['path'] = $modulePath;
        $builder->parameters['modules'][$this->name]['relativePath'] = rtrim($moduleRelativePath, DIRECTORY_SEPARATOR);
    }


    public function beforeCompile()
    {
        parent::beforeCompile();

        $builder = $this->getContainerBuilder();
        $config  = $this->getConfig();

        // zatím se budeme spoléhat na standardní nette tag
        if ($presenters = $builder->findByTag(self::TAG_DEVRUN_PRESENTER)) {

            $modules = $this->getModuleNameList($builder);

//            dump($presenters);
//            dump($modules);

//            die();

            foreach ($presenters as $presenter => $class) {
                $definition = $builder->getDefinition($presenter);
                $moduleName = Nette\Utils\Strings::before($presenter, '.');

                if (!in_array($moduleName, $modules)) {
                    throw new Devrun\InvalidArgumentException(sprintf("Presenter %s not register correctly, use correct existing modules [%s]",$presenter, implode(', ', $modules)));
                }

                if ($moduleName != $this->name) continue;

//                dump($this->name);
//                dump($definition->getClass());
//                dump($class);
//                dump($config);
//                dump($presenters);

                /**
                 * @deprecated
                 */
                if (method_exists($_class = $definition->getClass(), 'setWebLoaderCollections')) {
                    if (isset($config['webloader'])) {
                        $definition->addSetup('setWebLoaderCollections', [$config['webloader']]);
                    }
                }
            }
        }

    }

    /**
     * @param $tag
     * @return array
     */
    protected function getSortedServices($tag)
    {
        $builder = $this->getContainerBuilder();

        $items = array();
        $ret = array();
        foreach ($builder->findByTag($tag) as $route => $meta) {
            $priority = isset($meta['priority']) ? $meta['priority'] : (int)$meta;
            $items[$priority][] = $route;
        }

        krsort($items);

        foreach ($items as $items2) {
            foreach ($items2 as $item) {
                $ret[] = $item;
            }
        }
        return $ret;
    }


    /**
     * @param Nette\DI\ContainerBuilder $builder
     *
     * @return array
     */
    private function getModuleNameList(Nette\DI\ContainerBuilder $builder)
    {
        return array_keys($builder->parameters['modules']);
    }

}