<?php
/**
 * This file is part of devrun.
 * Copyright (c) 2017
 *
 * @file    InstallCommand.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\Module\Commands;

use Devrun\InvalidArgumentException;
use Devrun\Module\DependencyResolver\Job;
use Devrun\Module\IModule;
use Devrun\Module\ModuleFacade;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class InstallCommand extends Command
{

    /** @var ModuleFacade */
    private $moduleManager;


    /**
     * RegisterCommand constructor.
     *
     * @param ModuleFacade $moduleFacade
     */
    public function __construct(ModuleFacade $moduleFacade)
    {
        parent::__construct();
        $this->moduleManager = $moduleFacade;
    }


    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('devrun:module:install')
            ->addArgument('module', InputArgument::REQUIRED, 'Module name')
            ->addOption('noconfirm', NULL, InputOption::VALUE_NONE, 'do not ask for any confirmation')
            ->setDescription('Install module.');
    }


    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var $module IModule */
        $module = $this->moduleManager->createInstance($input->getArgument('module'));

        try {
            $problem = $this->moduleManager->testInstall($module);

        } catch (InvalidArgumentException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
            return;
        }

        if (!$input->getOption('noconfirm') && count($problem->getSolutions()) > 0) {
            $output->writeln("<info>install : {$module->getName()}</info>");
            foreach ($problem->getSolutions() as $job) {
                $output->writeln("<info>{$job->getAction()} : {$job->getModule()->getName()}</info>");
            }

            $dialog = $this->getHelperSet()->get('question');
            $question = new ConfirmationQuestion('<question>Continue with this actions? [y/N]</question> ', false);
            if (!$dialog->ask($input, $output, $question)) {
                return;
            }

        }

        try {
            foreach ($problem->getSolutions() as $job) {
                $this->moduleManager->doAction($job->getAction(), $job->getModule());

                if ($job->getAction() === Job::ACTION_INSTALL) {
                    $output->writeln("Module '{$job->getModule()->getName()}' has been installed.");
                } else if ($job->getAction() === Job::ACTION_UNINSTALL) {
                    $output->writeln("Module '{$job->getModule()->getName()}' has been uninstalled.");
                } else if ($job->getAction() === Job::ACTION_UPGRADE) {
                    $output->writeln("Module '{$job->getModule()->getName()}' has been upgraded.");
                }
            }
            $this->moduleManager->install($module);
            $this->moduleManager->onInstalled($this->moduleManager, $module);
            $output->writeln("Module '{$input->getArgument('module')}' has been installed.");
        } catch (InvalidArgumentException $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");
        }

    }



}