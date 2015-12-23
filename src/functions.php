<?php

use Speedwork\Container\Container;
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

/**
 * Fetch the IP address of the current visitor.
 *
 * @return string The IP address.
 */
function ip()
{
    return Utility::ip();
}

function _e($string, $replace = [])
{
    return trans($string, $replace);
}
