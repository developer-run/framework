<?php
/**
 * This file is part of devrun.
 * Copyright (c) 2017
 *
 * @file    Arrays.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\Utils;

use Nette\Utils\JsonException;

/**
 * Class Arrays
 *
 * @package Devrun\Utils
 */
class Arrays
{

    /**
     * merge two arrays with unique keys and values
     *
     * @param array $array1
     * @param array $array2
     *
     * @return array
     */
    public static function array_replace_recursive_ex(array & $array1, array & $array2)
    {
        $merged = $array1;

        foreach ($array2 as $key => & $value)
        {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = self::array_replace_recursive_ex($merged[$key], $value);

            } else if (is_numeric($key)) {
                if (!in_array($value, $merged))
                    $merged[] = $value;

            } else
                $merged[$key] = $value;
        }

        return $merged;
    }


    /**
     * @param $callback
     * @param $array
     *
     * @return array
     */
    public static function array_map_recursive($callback, $array)
    {
        $func = function ($item) use (&$func, &$callback) {
            return is_array($item) ? array_map($func, $item) : call_user_func($callback, $item, $item);
        };

        return array_map($func, $array);
    }


    /**
     * add value to array by array of keys
     *
     * @param $main_array
     * @param $keys
     * @param $value
     *
     * @return array
     */
    public static function addByArrayKeys($main_array, array $keys, $value){
        $tmp_array = &$main_array;
        while( count($keys) > 0 ){
            $k = array_shift($keys);
            if(!is_array($tmp_array)){
                $tmp_array = array();
            }
            $tmp_array = &$tmp_array[$k];
        }
        $tmp_array[] = $value;
        return $main_array;
    }


    public static function setByArrayKeys($main_array, array $keys, $value){
        $tmp_array = &$main_array;
        while( count($keys) > 0 ){
            $k = array_shift($keys);
            if(!is_array($tmp_array)){
                $tmp_array = array();
            }
            $tmp_array = &$tmp_array[$k];
        }
        $tmp_array = $value;
        return $main_array;
    }


    /**
     * check if array contains another array
     *
     * @param $array
     *
     * @return bool
     */
    public static function contains_array($array){
        foreach($array as $value){
            if(is_array($value)) {
                return true;
            }
        }
        return false;
    }


    public static function array_first_key($array) {
        if (count($array)) {
            reset($array);
            return key($array);
        }

        return null;
    }


    /*
     * thanks
     * https://stackoverflow.com/questions/3876435/recursive-array-diff
     */
    public static function arrayRecursiveDiff($aArray1, $aArray2) {
        $aReturn = array();

        foreach ($aArray1 as $mKey => $mValue) {
            if (array_key_exists($mKey, $aArray2)) {
                if (is_array($mValue)) {
                    $aRecursiveDiff = self::arrayRecursiveDiff($mValue, $aArray2[$mKey]);
                    if (count($aRecursiveDiff)) { $aReturn[$mKey] = $aRecursiveDiff; }
                } else {
                    if ($mValue != $aArray2[$mKey]) {
                        $aReturn[$mKey] = $mValue;
                    }
                }
            } else {
                $aReturn[$mKey] = $mValue;
            }
        }
        return $aReturn;
    }

    /**
     * transform string array to array
     *
     * @param string $stringArray
     * @return array
     * @throws JsonException
     */
    public static function stringArrayToArray(string $stringArray): array
    {
        if ($stringArray) {
            $_rawAttrs = preg_replace('%^\[%', '{', $stringArray);
            $_rawAttrs = preg_replace('%]$%', '}', $_rawAttrs);
            $_rawAttrs = str_replace('=>', ':', $_rawAttrs);
            $_rawAttrs = str_replace('\'[', '[', $_rawAttrs);
            $_rawAttrs = str_replace(']\'', ']', $_rawAttrs);
            $_rawAttrs = str_replace('\'{', '{', $_rawAttrs);
            $_rawAttrs = str_replace('}\'', '}', $_rawAttrs);
            $_rawAttrs = str_replace('\'', '"', $_rawAttrs);

            try {
                $attrs = json_decode($_rawAttrs, true, 512, JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR);
                return $attrs;

            } catch (\JsonException $exception) {
                $example1 = '{"package":true, "page":false, "route":true, "data-toolbar":{"heading": true, "bold": true}, "class":"text-primary", "id":"test"}';
                $example2 = '{"package":true, "page":false, "route":true, "data-toolbar":["heading", "bold", "italic"], "class":"text-primary", "id":"test"}';

                throw new JsonException($exception->getMessage() . ", you have $_rawAttrs \nExample1 $example1\nExample2 $example2");
            }
        }

        return [];
    }


}