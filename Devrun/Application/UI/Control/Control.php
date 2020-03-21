<?php
/**
 * This file is part of devrun-souteze.
 * Copyright (c) 2018
 *
 * @file    Control.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\Application\UI\Control;

use Kdyby\Translation\Translator;
use Nette;
use Nette\Application\UI\ITemplate;

abstract class Control extends Nette\Application\UI\Control
{


    /** @var Translator @inject */
    public $translator;



    public function render()
    {
        $this->template->render();
    }

    /**
     * @return \Nette\Templating\ITemplate
     */
    protected function createTemplate(string $class = null): ITemplate
    {
        $template = parent::createTemplate($class);
        $file     = $this->getTemplateFile();
        if (file_exists($file)) {
            $template->setFile($file);
        }

        return $template;
    }

    /**
     * @return string
     */
    protected function getTemplateFile()
    {
        $reflection = $this->getReflection();
        return dirname($reflection->getFileName()) . DIRECTORY_SEPARATOR . $reflection->getShortName() . '.latte';
    }


    protected function isAjax()
    {
        return $this->presenter->isAjax();
    }


    protected function ajaxRedirect()
    {
        if ($this->presenter->isAjax()) {
            $this->redrawControl();

        } else {
            $this->redirect('this');
        }


    }

}
