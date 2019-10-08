<?php
/**
 * This file is part of the devrun2016
 * Copyright (c) 2017
 *
 * @file    Debugger.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\Utils;


class Debugger extends \Tracy\Debugger
{

    public static function isConsole()
    {
        return PHP_SAPI === 'cli';
    }


    /**
     * @return string
     */
    public static function getIPAddress()
    {
        return $_SERVER["HTTP_X_REAL_IP"] ?? $_SERVER["HTTP_X_FORWARDED_FOR"] ?? $_SERVER["HTTP_CLIENT_IP"] ?? $_SERVER["REMOTE_ADDR"] ?? 'UNKNOWN';
    }


    /**
     * return true if docker environment is set
     * VIRTUAL_HOST | HOST
     *
     * @return bool
     */
    public static function isDocker()
    {
        return isset($_SERVER['VIRTUAL_HOST']);
    }


}