<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 *
 */

namespace Devrun\Utils;

use Devrun\FatalErrorException;
use Devrun\StaticClassException;

/**
 * Limited scope for PHP code evaluation and script including.
 *
 * @author     David Grudl => Nette deprecated
 */
final class LimitedScope
{
    private static $vars;

    /**
     * Static class - cannot be instantiated.
     */
    final public function __construct()
    {
        throw new StaticClassException();
    }


    /**
     * Evaluates code in limited scope.
     * @param string  PHP code
     * @param array   local variables
     * @return mixed   the return value of the evaluated code
     */
    public static function evaluate(/*$code, array $vars = NULL*/)
    {
        if (func_num_args() > 1) {
            self::$vars = func_get_arg(1);
            extract(self::$vars);
        }
        $res = eval('?>' . func_get_arg(0));
        if ($res === FALSE && ($error = error_get_last()) && $error['type'] === E_PARSE) {
            throw new FatalErrorException($error['message'], 0, $error['type'], $error['file'], $error['line'], NULL);
        }
        return $res;
    }


    /**
     * Includes script in a limited scope.
     * @param string  file to include
     * @param array   local variables or TRUE meaning include once
     * @return mixed   the return value of the included file
     */
    public static function load(/*$file, array $vars = NULL*/)
    {
        if (func_num_args() > 1) {
            self::$vars = func_get_arg(1);
            if (self::$vars === TRUE) {
                return include_once func_get_arg(0);
            }
            extract(self::$vars);
        }
        return include func_get_arg(0);
    }

}
