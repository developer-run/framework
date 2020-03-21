<?php
/**
 * This file is part of the devrun
 * Copyright (c) 2016
 *
 * @file    BasePresenter.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\Application\UI\Presenter;

use Devrun;
use Kdyby\Translation\Translator;
use Nette;
use Tracy\Debugger;

/**
 * Class BasePresenter
 *
 * @package Devrun\Application\UI\Presenter
 * @method onBeforeRender(Nette\Application\UI\Presenter $presenter)
 */
class BasePresenter extends Nette\Application\UI\Presenter
{

    const BEFORE_RENDER_EVENT = 'Devrun\Application\UI\Presenter\BasePresenter::onBeforeRender';

    /** @persistent */
    public $locale;

    /** @var Translator @inject */
    public $translator;

    /** @var Nette\Http\IRequest @inject */
    public $requestScript;

    /** @var bool */
    protected $enableAjaxLayout = true;

    /** @var bool */
    protected $cmsGarbageMode = false;

    /** @var callable[]  function (Presenter $sender); Occurs when the presenter is before render */
    public $onBeforeRender = [];


    protected function startup()
    {
        parent::startup();

        // login from administration
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $this->getUser()->login($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        }

        // layout off if post query
        if (($layout = $this->getHttpRequest()->getPost('layout')) !== NULL) {
            // $this->setLayout((boolean)$layout);

            if ($layout) {
                if ($layoutFile = $this->findLayoutTemplateFile()) {
                    $this->setLayout($layoutFile);
                }
            }

            $this->template->cms = true;
            Debugger::$showBar = false;
        }

//        if (Devrun\CmsModule\Utils\Common::isPhantomRequest()) {
//            Debugger::$productionMode = true;
//        }

        // for application cms garbage mode, can delete page img etc.
        if ((($cmsMode = $this->getHttpRequest()->getPost('cmsMode')) !== NULL) || (($cmsMode = $this->getParameter('cmsMode')) !== NULL)) {
            $this->cmsGarbageMode = $this->template->cmsGarbageMode = (bool)$cmsMode == Devrun\CmsModule\Administration\ICmsMode::GARBAGE_MODE;
        }

    }



    protected function ajaxLayout()
    {
        if ($this->isAjax() && !$this->enableAjaxLayout) $this->setLayout(false);
    }


    /**
     * ajax redirect
     *
     * @param string            $uri
     * @param null|string|array $controlsToRedraw
     * @param bool|string|array $redrawControls
     */
    public function ajaxRedirect($uri = 'this', $controlsToRedraw = null, $redrawControls = true)
    {
        if ($this->isAjax()) {
            if ($controlsToRedraw) {

                if (is_array($controlsToRedraw)) {
                    foreach ($controlsToRedraw as $controlToRedraw) {
                        if (isset($this[$controlToRedraw])) {
                            /** @var Nette\Application\UI\IRenderable $control */
                            $control = $this[$controlToRedraw];
                            if ($control instanceof Nette\Application\UI\IRenderable) {
                                $control->redrawControl();
                            }
                        }
                    }

                } else {
                    if (isset($this[$controlsToRedraw])) {
                        /** @var Nette\Application\UI\IRenderable $control */
                        $control = $this[$controlsToRedraw];
                        if ($control instanceof Nette\Application\UI\IRenderable) {
                            $control->redrawControl();
                        }
                    }
                }
            }

            if ($redrawControls) {
                if (is_array($redrawControls)) {
                    foreach ($redrawControls as $redrawControl) {
                        $this->redrawControl($redrawControl);
                    }

                } elseif (is_string($redrawControls)) {
                    $this->redrawControl($redrawControls);

                } else {
                    $this->redrawControl();
                }
            }

        } else {
            $this->redirect($uri);
        }
    }


    protected function beforeRender()
    {
        parent::beforeRender();

        $name = Nette\Utils\Strings::after($this->getName(), ":");
        $name .= ($this->action != 'default') ? $this->action : null;

        $this->template->robots = "index, follow";
        $this->template->pageClass = trim(strtolower("$name {$this->locale}"));
        $this->template->production = Debugger::$productionMode;

        $this->onBeforeRender($this);

        $this->ajaxLayout();
    }


    /**
     * signal to clear cache
     * @throws Nette\Application\AbortException
     */
    public function handleClearCache()
    {
        if ($dir = $this->getContext()->getParameters()['tempDir'] . '/cache') {
            opcache_reset();
            \Devrun\Utils\FileTrait::purge($dir = dirname(__DIR__) . "/temp/cache");
            $this->redirect('this');
        }

    }


    /**
     * @param $name
     * @param null $default
     * @return null
     */
    public function getContextParameter($name, $default = null)
    {
        $params = $this->getContext()->getParameters();
        return (isset($params[$name])) ? $params[$name] : $default;
    }

    /**
     * @return bool
     */
    public function isDebugMode()
    {
        return $this->getContextParameter('debugMode', false);
    }


    /**
     * @return bool
     */
    public function isCmsGarbageMode(): bool
    {
        return $this->cmsGarbageMode;
    }



}