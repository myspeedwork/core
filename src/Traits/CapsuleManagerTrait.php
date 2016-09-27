<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Core\Traits;

use Speedwork\Container\Container;

/**
 * @author Sankar <sankar.suda@gmail.com>
 */
trait CapsuleManagerTrait
{
    /**
     * The current globally used instance.
     *
     * @var object
     */
    protected static $instance;

    /**
     * The container instance.
     *
     * @var \Speedwork\Container\Container
     */
    protected $app;

    /**
     * Make this capsule instance available globally.
     */
    public function setAsGlobal()
    {
        static::$instance = $this;
    }

    /**
     * Get the IoC container instance.
     *
     * @return \Speedwork\Container\Container
     */
    public function getContainer()
    {
        return $this->app;
    }

    /**
     * Set the IoC container instance.
     *
     * @param \Speedwork\Container\Container $app
     */
    public function setContainer(Container $app)
    {
        $this->app = $app;
    }

    /**
     * magic method to get property.
     *
     * @param string $key value to get
     *
     * @return bool
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * get the key from stored data value.
     *
     * @param string $key The name of the variable to access
     *
     * @return mixed returns your stored value
     */
    public function get($key)
    {
        return $this->getContainer()->get($key);
    }

    /**
     * get the key from stored data value.
     *
     * @param string $key The name of the variable to access
     *
     * @return mixed returns your stored value
     */
    public function has($key)
    {
        return $this->getContainer()->has($key);
    }

    /**
     * store key value pair in registry.
     *
     * @param string $key   name of the variable
     * @param mixed  $value value to store in registry
     */
    public function set($key, $value)
    {
        $this->getContainer()->set($key, $value);

        return $this;
    }
}
