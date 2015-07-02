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
 * @author sankar <sankar.suda@gmail>
 */
class Di
{
    protected $post   = [];
    protected $get    = [];
    protected $data   = [];
    protected $server = [];
    protected $di;

    public function __construct()
    {
        $this->post   = &$_POST;
        $this->get    = &$_GET;
        $this->data   = &$_REQUEST;
        $this->server = &$_SERVER;
    }

    public function setContainer($di)
    {
        $this->di = $di;
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
        return Registry::get($key);
    }

    /**
     * store the values in registry.
     *
     * @param string $key   key to store
     * @param mixed  $value valye to store
     */
    public function __set($key, $value)
    {
        Registry::set($key, $value);
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
        return Registry::get($key);
    }

    /**
     * store key value pair in registry.
     *
     * @param string $key   name of the variable
     * @param mixed  $value value to store in registry
     */
    public function set($key, $value)
    {
        Registry::set($key, $value);
    }

    /**
     * sets is same as set but it set in registry and theme.
     *
     * @param string $key   name of the variable
     * @param mixed  $value value to store
     */
    public function sets($key, $value)
    {
        Registry::set($key, $value);
        $this->assign($key, $value);
    }

    public function assign($key, $value)
    {
        $this->get('tengine')->assignByRef($key, $value);
    }

    public function release($key)
    {
        return $this->get('tengine')->getTemplateVars($key);
    }
}
