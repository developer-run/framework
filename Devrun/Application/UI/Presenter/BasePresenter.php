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
     * @param string|array $uri
     * @param null|string|array $controls
     * @param bool|string|array $snippets
     * @throws Nette\Application\AbortException
     */
    public function ajaxRedirect($uri = 'this', $controls = null, $snippets = true)
    {
        if ($this->isAjax()) {
            if ($controls) {

                if (is_array($controls)) {
                    foreach ($controls as $controlToRedraw) {
                        if (isset($this[$controlToRedraw])) {
                            /** @var Nette\Application\UI\IRenderable $control */
                            $control = $this[$controlToRedraw];
                            if ($control instanceof Nette\Application\UI\IRenderable) {
                                $control->redrawControl();
                            }
                        }
                    }

                } else {
                    if (isset($this[$controls])) {
                        /** @var Nette\Application\UI\IRenderable $control */
                        $control = $this[$controls];
                        if ($control instanceof Nette\Application\UI\IRenderable) {
                            $control->redrawControl();
                        }
                    }
                }
            }

            if ($snippets) {
                if (is_array($snippets)) {
                    foreach ($snippets as $redrawControl) {
                        $this->redrawControl($redrawControl);
                    }

                } elseif (is_string($snippets)) {
                    $this->redrawControl($snippets);

                } else {
                    $this->redrawControl();
                }
            }

        } else {
            if (is_string($uri)) $this->redirect($uri);
            elseif (is_array($uri)) {
                if (count($uri) == 1) $this->redirect($uri[0]);
                elseif (count($uri) == 2) $this->redirect($uri[0], $uri[1]);
                elseif (count($uri) == 3) $this->redirect($uri[0], $uri[1], $uri[2]);
            }
        }
    }


    protected function beforeRender()
    {
        parent::beforeRender();

        $name = Nette\Utils\Strings::after($this->getName(), ":");
        $name .= ($this->action != 'default') ? $this->action : null;

        $this->template->locale = $this->locale;
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