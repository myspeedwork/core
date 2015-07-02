<?php

/**
 * This file is part of the Speedwork package.
 *
 * (c) 2s Technologies <info@2stechno.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Speedwork\Core;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Api extends Di
{
    /**
     * store status value.
     *
     * @var array
     */
    public $status = [];

    /**
     * holds the $_POST,$_GET AND $_REQUEST data values.
     *
     * @var array
     */
    public $data = [];
}
