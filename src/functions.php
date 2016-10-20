<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

use Speedwork\Container\Container;
use Speedwork\Util\Arr;
use Speedwork\Util\Str;
use Speedwork\Util\Utility;

/**
 * Get the available container instance.
 *
 * @param string $make
 * @param array  $parameters
 *
 * @return mixed|\Speedwork\Container\Container
 */
function app($name = null)
{
    if (is_null($name)) {
        return Container::getInstance();
    }

    if (is_array($name)) {
        foreach ($name as $innerKey => $innerValue) {
            return Container::getInstance()->set($innerKey, $innerValue);
        }
    }

    return Container::getInstance()->get($name);
}

/**
 * Helper fuction for configuration read and write.
 *
 * @param mixed $key     [description]
 * @param mixed $default [description]
 */
function config($key = null, $default = null)
{
    if (is_null($key)) {
        return app('config');
    }

    if (is_array($key)) {
        return app('config')->set($key);
    }

    return app('config')->get($key, $default);
}

/**
 * Helper function to read paths and locations of app.
 *
 * @param string $name  Name of the path or location
 * @param bool   $isUrl Is required location
 *
 * @return string
 */
function path($name, $isUrl = false)
{
    if ($isUrl) {
        return app('location.'.$name);
    }

    return app('path.'.$name);
}

/**
 * Get / set the specified session value.
 *
 * If an array is passed as the key, we will assume you want to set an array of values.
 *
 * @param array|string $key
 * @param mixed        $default
 *
 * @return mixed
 */
function session($key = null, $default = null)
{
    if (is_null($key)) {
        return app('session');
    }

    if (is_array($key)) {
        foreach ($key as $innerKey => $innerValue) {
            return app('session')->set($innerKey, $innerValue);
        }
    }

    return app('session')->get($key, $default);
}

function env($name = null, $default = null)
{
    if (is_null($name)) {
        return $_SERVER;
    }

    if (is_array($name)) {
        foreach ($name as $innerKey => $innerValue) {
            return $_SERVER[$innerKey] = $innerValue;
        }
    }

    return $_SERVER[$name] ?: $default;
}

function printr($array)
{
    $template = php_sapi_name() !== 'cli' ? '<pre>%s</pre>' : "\n%s\n";
    printf($template, print_r($array, true));
}

function salt($string)
{
    $salt = config('auth.salting');
    if ($salt === false) {
        return md5($string);
    }

    $string = trim($string);
    $hash   = uniqid(); //length 13

    return md5($string.$hash).$hash;
}

function unsalt($string, $salt)
{
    if (strlen($salt) == 32) {
        return md5($string);
    }

    if (strlen($salt) > 50) {
        return crypt($string, substr($salt, 0, 22));
    }

    $string = trim($string);

    $hash = substr($salt, -13);

    return md5($string.$hash).$hash;
}

function trans($string, $replace = [])
{
    if (is_array($replace)) {
        foreach ($replace as $k => $v) {
            $string = str_replace(':'.$k, $v, $string);
        }
    }

    return $string;
}

function slug($title, $seperator = '-')
{
    return Str::slug($title, $seperator);
}

function ip()
{
    return Utility::ip();
}

function strtime($time, $date = false, $format = 'Y-m-d')
{
    return Utility::strtotime($time, $date, $format);
}

/**
 * Set an item on an array or object using dot notation.
 *
 * @param mixed        $target
 * @param string|array $key
 * @param mixed        $value
 * @param bool         $overwrite
 *
 * @return mixed
 */
function data_set(&$target, $key, $value, $overwrite = true)
{
    $segments = is_array($key) ? $key : explode('.', $key);

    if (($segment = array_shift($segments)) === '*') {
        if (!Arr::accessible($target)) {
            $target = [];
        }

        if ($segments) {
            foreach ($target as &$inner) {
                data_set($inner, $segments, $value, $overwrite);
            }
        } elseif ($overwrite) {
            foreach ($target as &$inner) {
                $inner = $value;
            }
        }
    } elseif (Arr::accessible($target)) {
        if ($segments) {
            if (!Arr::exists($target, $segment)) {
                $target[$segment] = [];
            }

            data_set($target[$segment], $segments, $value, $overwrite);
        } elseif ($overwrite || !Arr::exists($target, $segment)) {
            $target[$segment] = $value;
        }
    } elseif (is_object($target)) {
        if ($segments) {
            if (!isset($target->{$segment})) {
                $target->{$segment} = [];
            }

            data_set($target->{$segment}, $segments, $value, $overwrite);
        } elseif ($overwrite || !isset($target->{$segment})) {
            $target->{$segment} = $value;
        }
    } else {
        $target = [];

        if ($segments) {
            data_set($target[$segment], $segments, $value, $overwrite);
        } elseif ($overwrite) {
            $target[$segment] = $value;
        }
    }

    return $target;
}

/**
 * Get an item from an array or object using "dot" notation.
 *
 * @param mixed        $target
 * @param string|array $key
 * @param mixed        $default
 *
 * @return mixed
 */
function data_get($target, $key, $default = null)
{
    if (is_null($key)) {
        return $target;
    }

    $key = is_array($key) ? $key : explode('.', $key);

    while (($segment = array_shift($key)) !== null) {
        if ($segment === '*') {
            if ($target instanceof Collection) {
                $target = $target->all();
            } elseif (!is_array($target)) {
                return Str::value($default);
            }

            $result = Arr::pluck($target, $key);

            return in_array('*', $key) ? Arr::collapse($result) : $result;
        }

        if (Arr::accessible($target) && Arr::exists($target, $segment)) {
            $target = $target[$segment];
        } elseif (is_object($target) && isset($target->{$segment})) {
            $target = $target->{$segment};
        } else {
            return Str::value($default);
        }
    }

    return $target;
}
