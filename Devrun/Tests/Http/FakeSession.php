<?php

/**
 * This file is part of the Devrun (http://www.Devrun.org)
 */

namespace Devrun\Tests\Http;

use Devrun;
use Nette;


/**
 * @author Pavel Paulík <pavel@paulik.seznam.cz>
 */
class FakeSession extends Nette\Http\Session
{
    /** @var \Devrun\Tests\Http\FakeSessionSection[] */
    private $sections = array();

    /** @var bool */
    private $started = FALSE;

    /** @var array */
    private $options = array();

    /** @var string */
    private $id;

    /** @var string */
    private $name = 'session_id';


    /**
     * @param \Nette\Http\IRequest  $request
     * @param \Nette\Http\IResponse $response
     */
    public function __construct(Nette\Http\IRequest $request, Nette\Http\IResponse $response)
    {
        $this->regenerateId();
    }


    /**
     */
    public function start(): void
    {
        $this->started = TRUE;
    }


    /**
     * @return bool
     */
    public function isStarted(): bool
    {
        return $this->started;
    }


    /**
     */
    public function close(): void
    {
        $this->started = NULL;
    }


    /**
     */
    public function destroy(): void
    {
        $this->sections = array();
        $this->close();
    }


    /**
     * @return bool
     */
    public function exists(): bool
    {
        return TRUE;
    }


    /**
     *
     */
    public function regenerateId(): void
    {
        $this->id = md5((string)microtime(TRUE));
    }


    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }


    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }


    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * @param string $section
     * @param string $class
     *
     * @return \Devrun\Tests\Http\FakeSessionSection
     */
    public function getSection($section, $class = 'Devrun\Tests\Http\FakeSessionSection'): Nette\Http\SessionSection
    {
        if (!isset($this->sections[$section])) $this->sections[$section] = new $class($this, $section);
        return $this->sections[$section];
    }


    /**
     * @deprecated
     *
     * @param $section
     *
     * @throws \Devrun\NotSupportedException
     */
    public function getNamespace($section)
    {
        throw new Devrun\NotSupportedException;
    }


    /**
     * @param string $section
     *
     * @return bool
     */
    public function hasSection($section): bool
    {
        return isset($this->sections[$section]);
    }


    /**
     * @return \ArrayIterator
     */
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->sections);
    }


    /**
     */
    public function clean(): void
    {

    }


    /**
     * @param array $options
     */
    public function setOptions(array $options)
    {
        $this->options = $options + $this->options;
    }


    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }


    /**
     * @param int|string $time
     */
    public function setExpiration($time)
    {

    }


    /**
     * @return array
     */
    public function getCookieParameters(): array
    {
        $keys  = array('cookie_path', 'cookie_domain', 'cookie_secure');
        $empty = array_fill_keys($keys, NULL);

        return array_intersect_key($this->options, $empty) + $empty;
    }


    /**
     * @param \Nette\Http\ISessionStorage $storage
     */
    public function setStorage(Nette\Http\ISessionStorage $storage)
    {

    }

}