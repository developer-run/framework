<?php
/**
 * This file is part of the devrun
 * Copyright (c) 2020
 *
 * @file    CoreExtension.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\DI;

use Devrun\Listeners\ComposerListener;
use Devrun\Listeners\MigrationListener;
use Devrun\Module\Providers\IPresenterMappingProvider;
use Devrun\Module\Providers\IRouterMappingProvider;
use Devrun\Security\ControlVerifierReaders\AnnotationReader;
use Devrun\Security\ControlVerifiers\ControlVerifier;
use Devrun\Security\User;
use Exception as ExceptionAlias;
use Kdyby\Console\DI\ConsoleExtension;
use Kdyby\Events\DI\EventsExtension;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\Reflection\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nette\Utils\Validators;

class CoreExtension extends CompilerExtension
{
    const TAG_ROUTER = 'devrun.modules.router';

    public function getConfigSchema(): Schema
    {
        return Expect::structure([
            'pageStorageExpiration' => Expect::string('5 hours'),
            'composerUpdate' => Expect::bool(true)->required(false),
            'composerWrite' => Expect::bool(false),
            'composerTags' => Expect::string('--no-interaction --ansi'),
            'migrationUpdate' => Expect::bool(true)->required(false),
        ]);
    }


    public function loadConfiguration()
    {
        /** @var ContainerBuilder $builder */
        $builder = $this->getContainerBuilder();

        /** @var \stdClass $config */
        $config  = $this->getConfig();


        // repositories


        // facades
        $builder->addDefinition($this->prefix('facade.module'))
                ->setType('Devrun\Module\ModuleFacade')
                ->addSetup('setPageStorageExpiration', [$config->pageStorageExpiration]);


        // system
        $builder->addDefinition($this->prefix('controlVerifier'))
                ->setType(ControlVerifier::class);

        $builder->addDefinition($this->prefix('controlVerifierReader'))
                ->setType(AnnotationReader::class);

        $builder->getDefinition('security.user')
                ->setFactory(User::class);

//        $builder->addDefinition($this->prefix('authorizator'))
//                ->setType('Devrun\Security\Authorizator');

//        $builder->addDefinition($this->prefix('authenticator'))
//                ->setType('Devrun\Security\Authenticator')
//                ->setInject();

        // http
        $builder->getDefinition('httpResponse')
                  ->addSetup('setHeader', array('X-Powered-By', 'Nette Framework && Devrun:Framework'));

        // Commands
        $commands = array(
            // 'cache' => 'Devrun\Caching\Commands\Cache',
            'moduleUpdate'     => 'Devrun\Module\Commands\Update',
            'moduleInstall'    => 'Devrun\Module\Commands\Install',
            'moduleUninstall'  => 'Devrun\Module\Commands\Uninstall',
            'moduleUpgrade'    => 'Devrun\Module\Commands\Upgrade',
            'moduleRegister'   => 'Devrun\Module\Commands\Register',
            'moduleUnregister' => 'Devrun\Module\Commands\UnRegister',
            'moduleList'       => 'Devrun\Module\Commands\List',
            'moduleCreate'     => 'Devrun\Module\Commands\Create',
            'moduleDelete'     => 'Devrun\Module\Commands\Delete',
        );
        foreach ($commands as $name => $cmd) {
            $builder->addDefinition($this->prefix(lcfirst($name) . 'Command'))
                    ->setFactory("{$cmd}Command")
                    ->addTag(ConsoleExtension::TAG_COMMAND);
        }

        // Modules provider
        foreach ($this->compiler->getExtensions() as $extension) {
            /*
            if ($extension instanceof IParametersProvider) {
                $this->setupParameters($extension);
            }
            */

            if ($extension instanceof IPresenterMappingProvider) {
                $this->setupPresenterMapping($extension);
            }

            if ($extension instanceof IRouterMappingProvider) {
                $this->setupRouter($extension);
            }

            /*
            if ($extension instanceof ILatteMacrosProvider) {
                $this->setupMacros($extension);
            }

            if ($extension instanceof ITemplateHelpersProvider) {
                $this->setupHelpers($extension);
            }

            if ($extension instanceof IErrorPresenterProvider){
                $this->setupErrorPresenter($extension);
            }
            */
        }

        // Subscribers
        $builder->addDefinition($this->prefix('subscriber.composer'))
                ->setFactory(ComposerListener::class, [$config->composerUpdate, $config->composerTags, $config->composerWrite])
                ->addTag(EventsExtension::TAG_SUBSCRIBER);

        $builder->addDefinition($this->prefix('subscriber.migration'))
                ->setFactory(MigrationListener::class, [$config->migrationUpdate])
                ->addTag(EventsExtension::TAG_SUBSCRIBER);


    }

    public function beforeCompile()
    {
        /** @var ContainerBuilder $builder */
        $builder = $this->getContainerBuilder();

        // set module paths
        $moduleFacade = $builder->getDefinition($this->prefix('facade.module'));
        $moduleFacade->addSetup('setModulesPath', [$builder->parameters['modules']]);

        $this->checkDirStructure();
    }


    private function checkDirStructure()
    {
        $builder     = $this->getContainerBuilder();
        $systemPaths = [
            $builder->parameters['wwwCacheDir'],
        ];

        foreach ($systemPaths as $systemPath) {
            if (!is_dir($systemPath)) {
                try {
                    mkdir($systemPath, 0777, true);

                } catch (ExceptionAlias $e) {
                    die($e->getMessage());
                }
            }
        }

    }


    private function setupPresenterMapping(IPresenterMappingProvider $extension)
    {
        $mapping = $extension->getPresenterMapping();
        Validators::assert($mapping, 'array', 'mapping');

        if (count($mapping)) {
            $this->getContainerBuilder()->getDefinition('nette.presenterFactory')
                 ->addSetup('setMapping', array($mapping));
        }
    }


    private function setupRouter(IRouterMappingProvider $extension)
    {
        $builder = $this->getContainerBuilder();
        $router = $builder->getDefinition('router');

        /** @var CompilerExtension $extension */
        $name = $this->addRouteService(ClassType::from($extension)->getName());
        $router->addSetup('offsetSet', array(NULL, $name));
    }

    /**
     * @param string $class
     * @return string
     */
    private function addRouteService($class)
    {
        $serviceName = md5($class);
        $builder = $this->getContainerBuilder();

        $builder->addDefinition($this->prefix('routeService.' . $serviceName))
                ->setClass($class)
                ->setInject(TRUE);

        $builder->addDefinition('routerServiceFactory.' . $serviceName)
                ->setFactory($this->prefix('@routeService.' . $serviceName) . '::getRoutesDefinition')
                ->setAutowired(FALSE);

        return '@routerServiceFactory.' . $serviceName;
    }



}