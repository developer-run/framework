<?php
/**
 * This file is part of the devrun
 * Copyright (c) 2016
 *
 * @file    Exceptions.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun;

use Nette\IOException;


/**
 * @author Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */
interface Exception
{

}



/**
 * @author Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */
class InvalidStateException extends \RuntimeException implements Exception
{

}



/**
 * @author Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */
class OutOfRangeException extends \OutOfRangeException implements Exception
{

}



/**
 * @author Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */
class NotSupportedException extends \LogicException implements Exception
{

}


/**
 * @author Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */
class InvalidArgumentException extends \InvalidArgumentException implements Exception
{

}


/**
 * @author Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */
class ApplicationException extends \Nette\Application\ApplicationException implements Exception
{

}


/**
 * @author Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */
class FileNotFoundException extends IOException implements Exception
{
}


/**
 * @author Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */
class ClassNotFoundException extends IOException implements Exception
{
}


/**
 * @author Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */
class ServiceNotFoundException extends \RuntimeException implements Exception
{
}


/**
 * @author Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */
class StaticClassException extends \LogicException implements Exception
{
}


/**
 * @author Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */
class FatalErrorException extends \ErrorException
{
    public function __construct($message, $code, $severity, $filename, $lineno, $traceOffset = null, $traceArgs = true, array $trace = null, $previous = null)
    {
        parent::__construct($message, $code, $severity, $filename, $lineno, $previous);

        if (null !== $trace) {
            if (!$traceArgs) {
                foreach ($trace as &$frame) {
                    unset($frame['args'], $frame['this'], $frame);
                }
            }

            $this->setTrace($trace);
        } elseif (null !== $traceOffset) {
            if (\function_exists('xdebug_get_function_stack')) {
                $trace = xdebug_get_function_stack();
                if (0 < $traceOffset) {
                    array_splice($trace, -$traceOffset);
                }

                foreach ($trace as &$frame) {
                    if (!isset($frame['type'])) {
                        // XDebug pre 2.1.1 doesn't currently set the call type key http://bugs.xdebug.org/view.php?id=695
                        if (isset($frame['class'])) {
                            $frame['type'] = '::';
                        }
                    } elseif ('dynamic' === $frame['type']) {
                        $frame['type'] = '->';
                    } elseif ('static' === $frame['type']) {
                        $frame['type'] = '::';
                    }

                    // XDebug also has a different name for the parameters array
                    if (!$traceArgs) {
                        unset($frame['params'], $frame['args']);
                    } elseif (isset($frame['params']) && !isset($frame['args'])) {
                        $frame['args'] = $frame['params'];
                        unset($frame['params']);
                    }
                }

                unset($frame);
                $trace = array_reverse($trace);
            } elseif (\function_exists('symfony_debug_backtrace')) {
                $trace = symfony_debug_backtrace();
                if (0 < $traceOffset) {
                    array_splice($trace, 0, $traceOffset);
                }
            } else {
                $trace = [];
            }

            $this->setTrace($trace);
        }
    }

    protected function setTrace($trace)
    {
        $traceReflector = new \ReflectionProperty('Exception', 'trace');
        $traceReflector->setAccessible(true);
        $traceReflector->setValue($this, $trace);
    }
}
