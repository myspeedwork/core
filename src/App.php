<?php

/**
 * This file is part of the Speedwork package.
 *
 * (c) 2s Technologies <info@2stech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Speedwork\Core;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class App
{
    /**
     * Holds key/value pairs of $type => file path.
     *
     * @var array
     */
    protected static $_map = [];

    /**
     * Holds the location of each class.
     *
     * @var array
     */
    protected static $_classMap = [];

    /**
     * Declares a package for a class. This package location will be used
     * by the automatic class loader if the class is tried to be used.
     *
     * Usage:
     *
     * `App::uses('MyCustomController', 'Controller');` will setup the class to be found under Controller package
     *
     * `App::uses('MyHelper', 'MyPlugin.View/Helper');` will setup the helper class to be found in plugin's helper package
     *
     * @param string $className the name of the class to configure package for
     * @param string $location  the package name
     *
     * @link http://book.cakephp.org/2.0/en/core-utility-libraries/app.html#App::uses
     */
    public static function uses($className, $location)
    {
        self::$_classMap[$className] = $location;
    }

    /**
     * Method to handle the automatic class loading. It will look for each class' package
     * defined using App::uses() and with this information it will resolve the package name to a full path
     * to load the class from. File name for each class should follow the class name. For instance,
     * if a class is name `MyCustomClass` the file name should be `MyCustomClass.php`.
     *
     * @param string $className the name of the class to load
     *
     * @return bool
     */
    public static function load($className)
    {
        if (strpos(self::$_classMap[$className], '\\') !== false) {
            class_alias(self::$_classMap[$className], $className);

            return true;
        }

        if (!isset(self::$_classMap[$className])) {
            return false;
        }
        if (strpos($className, '..') !== false) {
            return false;
        }

        $package = self::$_classMap[$className];

        $file = self::$_map[$className];
        if (isset($file)) {
            return include $file;
        }

        $paths   = [];
        $paths[] = SYS.$package.DS;
        $paths[] = APP.'system'.DS.$package.DS;

        $normalized = str_replace('\\', DS, $className);
        foreach ($paths as $path) {
            $file = $path.$normalized.'.php';
            if (file_exists($file)) {
                self::$_map[$className] = $file;

                return include $file;
            }
        }

        return false;
    }

    public static function imports($path, $system = true)
    {
        static $_render = [];

        $signature = md5($path);

        if (isset($_render[$signature])) {
            return true;
        }

        $_render[$signature] = true;

        $path = explode('.', $path);

        if ($system) {
            $fullpath    = _SYS_DIR.implode(DS, $path).'.php';
        } else {
            $fullpath    = APP.implode(DS, $path).'.php';
        }

        include_once $fullpath;
    }
}
