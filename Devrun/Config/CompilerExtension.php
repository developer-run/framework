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
use Nette\DI\ContainerBuilder;

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


    /**
     * FrontModule\DI\FrontExtension -> front
     *
     * @return string
     */
    protected function getExtensionName(): string
    {
        return strtolower(substr(strrchr(get_class($this), "\\"), 1, - strlen("extension")));
    }


    /**
     * 
     * 
     * @return string|null
     */
    protected function getPath(): ?string
    {
        /** @var ContainerBuilder $builder */
        $builder = $this->getContainerBuilder();

        return $builder->parameters['modules'][$this->getExtensionName()]['path'] ?? null;
    }
    
    
    /**
     * return sorted services by priority
     *
     * @param string|array $tag  [ tag,  array(tag1, tag2)  ]
     * @return array
     */
    protected function getSortedServices($tag): array
    {
        $builder = $this->getContainerBuilder();

        $ret   = array();
        $items = array();

        if (is_array($tag)) {
            $services =  array_map( function ($service_tag) use ($builder) {
                return $builder->findByTag($service_tag);

            }, $tag);

            $mergeServices = [];
            array_walk($services, function ($service) use (&$mergeServices) {
                $mergeServices = array_merge($mergeServices, $service);
            });

            $services = $mergeServices;

        } else {
            $services = $builder->findByTag($tag);
        }

        foreach ($services as $route => $meta) {
            $priority = $meta['priority'] ?? (int)$meta;
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