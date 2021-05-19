<?php


namespace Devrun\Listeners;

use Devrun\Migrations\Migration;
use Devrun\Module\IModule;
use Devrun\Module\ModuleFacade;
use Kdyby\Events\Subscriber;

/**
 * Class MigrationListener
 * @package Devrun\Listeners
 */
class MigrationListener implements Subscriber
{

    /** @var bool */
    private $migrationUpdate = true;

    /**
     * MigrationListener constructor.
     * @param bool $migrationUpdate
     */
    public function __construct(bool $migrationUpdate)
    {
        $this->migrationUpdate = $migrationUpdate;
    }


    /**
     * @param ModuleFacade $moduleFacade
     * @throws \Nextras\Migrations\Exception
     */
    public function onUpdate(ModuleFacade $moduleFacade)
    {
        if ($this->migrationUpdate) {
            Migration::continue($moduleFacade->getContext());
        }
    }


    /**
     * @param ModuleFacade $moduleFacade
     * @param IModule $module
     * @throws \Nextras\Migrations\Exception
     */
    public function onInstalled(ModuleFacade $moduleFacade, IModule $module)
    {
        if ($this->migrationUpdate) {
            Migration::continue($moduleFacade->getContext());
        }
    }


    /**
     * higher number of priority is better for previously start
     *
     * @return array
     */
    function getSubscribedEvents()
    {
        return [
            "Devrun\Module\ModuleFacade::onUpdate" => ['onUpdate', 10],
            "Devrun\Module\ModuleFacade::onInstalled",
        ];
    }


}