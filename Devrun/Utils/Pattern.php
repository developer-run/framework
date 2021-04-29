<?php
/**
 * This file is part of souteze.pixman.cz.
 * Copyright (c) 2019
 *
 * @file    Pattern.php
 * @author  Pavel Paulík <pavel.paulik@support.etnetera.cz>
 */

namespace Devrun\Utils;


interface Pattern
{
    const HTTP_URL = "(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]";
    const URL = "(www\.)*[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]";
    const ANALYTICS = "(GTM-|UA-).*";
    const DATE = "^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$";
    const DATETIME = "^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])\s{1}(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$";
    const PHONE = "^(\+420|\+421)? ?[1-9][0-9]{2} ?[0-9]{3} ?[0-9]{3}$";


}