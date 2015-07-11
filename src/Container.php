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

use Pimple\Container as BaseContainer;

/**
 * @author sankar <sankar.suda@gmail>
 */
class Container extends BaseContainer
{
    /**
     * @param string $id
     *
     * @return bool
     */
    public function has($id)
    {
        return isset($this[$id]);
    }

    /**
     * @param string $id
     *
     * @return mixed
     */
    public function get($id)
    {
        return $this[$id];
    }

    /**
     * @param string $id
     *
     * @return mixed
     */
    public function set($id, $value = null)
    {
        return $this[$id] = $value;
    }

    /**
     * Magic method to get property.
     *
     * @param string $key value to get
     *
     * @return bool
     */
    public function __set($id, $value = null)
    {
        return $this->set($id, $value);
    }

    /**
     * Magic method to get property.
     *
     * @param string $key value to get
     *
     * @return bool
     */
    public function __get($key)
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        return Registry::get($key);
    }



    /**
     * Gets a parameter or an object.
     *
     * @param string $id The unique identifier for the parameter or object
     *
     * @return mixed The value of the parameter or an object
     *
     * @throws \InvalidArgumentException if the identifier is not defined
     */
    public function offsetGet($id)
    {
        if (!isset($this->keys[$id])) {
            return;
        }

        return parent::offsetGet($id);
    }

    /**
     * Registers a service provider.
     *
     * @param ServiceProviderInterface $provider A ServiceProviderInterface instance
     * @param array                    $values   An array of values that customizes the provider
     *
     * @return static
     */
    public function register(ServiceProvider $provider, array $values = [])
    {
        $provider->register($this);

        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }

        return $this;
    }
}
