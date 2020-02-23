<?php
/**
 * This file is part of the devrun2020
 * Copyright (c) 2020
 *
 * @file    Filters.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\Utils;

use Latte\Runtime\Html;
use Nette\Utils\Json;

/**
 * Class LatteFilters
 * latte filters
 *
 * @package Devrun\Utils
 *
 * config.neon
 * services:
 *  latte.latteFactory:
 *      setup:
 *          - addFilter(json, Devrun\Utils\Filters::json)
 */
class Filters
{

    /**
     * @param $data
     * @return Html
     * @throws \Nette\Utils\JsonException
     */
    public static function json($data): Html
    {
        return new Html(Json::encode($data));
    }

}