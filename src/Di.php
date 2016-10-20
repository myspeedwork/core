<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Core;

use Speedwork\Core\Traits\CapsuleManagerTrait;
use Speedwork\Core\Traits\EventDispatcherTrait;
use Speedwork\Core\Traits\FilterTrait;

/**
 * @author sankar <sankar.suda@gmail>
 */
class Di
{
    use EventDispatcherTrait;
    use CapsuleManagerTrait;
    use FilterTrait;

    /**
     * Read and write onfiguration.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function config($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->get('config');
        }

        if (is_array($key)) {
            return $this->get('config')->set($key);
        }

        return $this->get('config')->get($key, $default);
    }

    /**
     * Assign key value pairs to view.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return \Speedwork\Core\Di
     */
    public function assign($key, $value)
    {
        $this->get('engine')->assign($key, $value);

        return $this;
    }

    /**
     * Retrive item from view.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function release($key)
    {
        return $this->get('engine')->release($key);
    }

    /**
     * sets is same as set but it set in registry and theme.
     *
     * @param string $key   name of the variable
     * @param mixed  $value value to store
     */
    public function sets($key, $value)
    {
        $this->set($key, $value);
        $this->assign($key, $value);

        return $this;
    }

    /**
     * Short method to get resolver.
     *
     * @return \Speedwork\Core\Resolver Resolver object
     */
    public function resolver()
    {
        return $this->get('resolver');
    }

    /**
     * Generate Proper link.
     *
     * @param string $url
     *
     * @return string
     */
    public function link($url)
    {
        return Router::link($url);
    }
}
