<?php
/**
 * This file is part of the devrun2016
 * Copyright (c) 2017
 *
 * @file    ComposerModule.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\Module;


use Nette\Reflection\ClassType;
use Tracy\Debugger;
use Tracy\ILogger;

class ComposerModule extends BaseModule
{

    /** @var array */
    protected $composerData;


    /**
     * @return string
     */
    public function getName()
    {
        $this->loadComposerData();

        return $this->normalizeName($this->composerData['name']);
    }


    /**
     * @return mixed
     */
    public function getDescription()
    {
        $this->loadComposerData();

        return $this->composerData['description'];
    }


    /**
     * @return array
     */
    public function getKeywords()
    {
        $this->loadComposerData();

        return array_map('trim', explode(',', $this->composerData['keywords']));
    }


    /**
     * @return array
     */
    public function getLicense()
    {
        $this->loadComposerData();

        return $this->composerData['license'];
    }


    /**
     * @return string
     */
    public function getVersion()
    {
        $this->loadComposerData();

        if (isset($this->composerData['version'])) {
            return $this->composerData['version'];

        } else {

            if ($isGit = is_dir($this->getGitPath() . "/.git")) {
                chdir($this->getGitPath());

                if (function_exists("exec")) {
                    if ($gitVersion = exec('git describe --tags --always')) {
                        return $gitVersion;
                    }
                } else {
                    return "?";
                }

            } else {
                Debugger::log("`{$this->getName()}` module has not version, specify this in composer or overflow getGitPath in Module.php", ILogger::WARNING);
            }
        }

        return parent::getVersion() ?: "`unknown`";
    }


    /**
     * @return array
     */
    public function getAuthors()
    {
        $this->loadComposerData();

        return $this->composerData['authors'];
    }


    /**
     * @return array
     */
    public function getAutoload()
    {
        $this->loadComposerData();

        $ret = isset($this->composerData['autoload']) ? $this->composerData['autoload'] : array();

        if (file_exists(dirname(ClassType::from($this)->getFileName()) . '/static/autoload.php')) {
            return array_merge($ret, array(
                'files' => array('static/autoload.php'),
            ));
        }

        return $ret;
    }


    /**
     * @return array
     */
    public function getRequire()
    {
        $this->loadComposerData();

        $ret = array();
        foreach ($this->composerData['require'] as $name => $require) {
            if (substr($name, -7) === '-module') {

                if (substr($require, -4) === '-dev') {
                    $require = substr($require, 0, -4);
                }

                $ret[$this->normalizeName($name)] = str_replace('*', 'x', $require);
            }
        }

        return $ret;
    }


    /**
     * @return string
     */
    public function getRelativePublicPath()
    {
        $this->loadComposerData();

        if (isset($this->composerData['extra']['devrun']['relativePublicPath'])) {
            return $this->composerData['extra']['devrun']['relativePublicPath'];
        }

        return parent::getRelativePublicPath();
    }


    public function hasPublishedPages()
    {
        $this->loadComposerData();

        if (isset($this->composerData['extra']['devrun']['hasPublishedPages'])) {
            return $this->composerData['extra']['devrun']['hasPublishedPages'];
        }

        return parent::hasPublishedPages();
    }


    public function hasPackagePages()
    {
        $this->loadComposerData();

        if (isset($this->composerData['extra']['devrun']['hasPackagePages'])) {
            return $this->composerData['extra']['devrun']['hasPackagePages'];
        }

        return parent::hasPublishedPages();
    }


    /**
     * @return array
     */
    public function getExtra()
    {
        $this->loadComposerData();

        return $this->composerData['extra'];
    }


    /**
     * @return array
     */
    public function getConfiguration()
    {
        $this->loadComposerData();

        if (isset($this->composerData['extra']['devrun']['configuration'])) {
            return $this->composerData['extra']['devrun']['configuration'];
        }

        return parent::getConfiguration();
    }


    /**
     * @return array
     */
    public function getInstallers()
    {
        $this->loadComposerData();

        if (isset($this->composerData['extra']['devrun']['installers'])) {
            return array_merge(parent::getInstallers(), $this->composerData['extra']['devrun']['installers']);
        }

        return parent::getInstallers();
    }


    protected function loadComposerData()
    {
        if ($this->composerData === NULL) {
            $this->composerData = json_decode(file_get_contents($this->getPath() . '/composer.json'), TRUE);
        }
    }


    /**
     * @param $name
     * @return string
     */
    protected function normalizeName($name)
    {
        return substr($name, strpos($name, '/') + 1, -7);
    }
}
