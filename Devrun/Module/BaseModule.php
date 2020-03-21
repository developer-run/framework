<?php
/**
 * This file is part of the devrun2016
 * Copyright (c) 2017
 *
 * @file    BaseModule.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\Module;

use Nette\Reflection\ClassType;
use Nette\SmartObject;

class BaseModule implements IModule
{

    use SmartObject;

    /** @var string */
    protected $name;

    /** @var string */
    protected $version;

    /** @var string */
    protected $description;

    /** @var string */
    protected $defaultPageName;

    /** @var bool */
    protected $hasPublishedPages = false;

    /** @var bool */
    protected $hasPackagePages = false;

    /** @var array */
    protected $keywords = array();

    /** @var array */
    protected $license = array();

    /** @var array */
    protected $authors = array();


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }


    /**
     * @return string
     */
    public function getDefaultPageName()
    {
        return $this->defaultPageName;
    }


    /**
     * @return array
     */
    public function getKeywords()
    {
        return $this->keywords;
    }


    /**
     * @return array
     */
    public function getLicense()
    {
        return $this->license;
    }


    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }


    /**
     * @return array
     */
    public function getAuthors()
    {
        return $this->authors;
    }


    /**
     * @return array
     */
    public function getAutoload()
    {
        return array();
    }


    /**
     * @return array
     */
    public function getRequire()
    {
        return array();
    }


    /**
     * @return array
     */
    public function getConfiguration()
    {
        return array();
    }


    /**
     * @return array
     */
    public function getExtra()
    {
        return array();
    }

    /**
     * @return boolean
     */
    public function hasPublishedPages()
    {
        return $this->hasPublishedPages;
    }

    /**
     * @return boolean
     */
    public function hasPackagePages()
    {
        return $this->hasPackagePages;
    }


    /**
     * @return string
     */
    public function getPath()
    {
        return dirname(ClassType::from($this)->getFileName());
    }


    /**
     * @return string
     */
    public function getGitPath()
    {
        return $this->getPath();
    }


    /**
     * @return string
     */
    public function getRelativePublicPath()
    {
        return '/resources/public';
    }

    /**
     * @return string
     */
    public function getRelativePackagePath()
    {
        return '/resources/packages';
    }


    /**
     * @return string
     */
    public function getClassName()
    {
        return get_class($this);
    }


    /**
     * @return array
     */
    public function getInstallers()
    {
        return array('Devrun\Module\Installers\BaseInstaller');
    }


}