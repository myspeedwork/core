<?php

/**
 * This file is part of the Speedwork package.
 *
 * @link http://github.com/speedwork
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Speedwork\Core;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
final class Registry
{
    private static $data = [];

    public static function get($key, $default = null)
    {
        return (isset(self::$data[$key]) ? self::$data[$key] : $default);
    }

    public static function gets($key, $val)
    {
        return (isset(self::$data[$key][$val]) ? self::$data[$key][$val] : null);
    }

    public static function set($key, $value)
    {
        self::$data[$key] = $value;
    }

    public static function has($key)
    {
        return isset(self::$data[$key]);
    }
}
