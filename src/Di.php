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

use Speedwork\Config\Configure;
use Speedwork\Container\Container;
use Speedwork\Util\Router;

/**
 * @author sankar <sankar.suda@gmail>
 */
class Di
{
    //use EventManagerTrait;

    protected $post   = [];
    protected $get    = [];
    protected $data   = [];
    protected $server = [];
    protected $cookie = [];
    protected $di     = [];

    public function __construct(Container $di = null)
    {
        $this->di     = $di;
        $this->post   = &$_POST;
        $this->get    = &$_GET;
        $this->data   = &$_REQUEST;
        $this->server = &$_SERVER;
        $this->cookie = &$_COOKIE;
    }

    public function setContainer(Container $di)
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
        if ($this->di->has($key)) {
            return $this->di->get($key);
        }

        return Registry::get($key);
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
        return $this->di->has($key);
    }

    /**
     * store key value pair in registry.
     *
     * @param string $key   name of the variable
     * @param mixed  $value value to store in registry
     */
    public function set($key, $value)
    {
        $this->di->set($key, $value);

        return $this;
    }

    /**
     * store key value pair in registry.
     *
     * @param string $key   name of the variable
     * @param mixed  $value value to store in registry
     */
    public function read($key)
    {
        return Configure::read($key);
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
        Registry::set($key, $value);

        return $this;
    }

    public function assign($key, $value)
    {
        $this->get('engine')->assign($key, $value);

        return $this;
    }

    public function release($key)
    {
        return $this->get('engine')->release($key);
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Redirect the url.
     *
     * @param [type] $url  [description]
     * @param int    $time [description]
     * @param bool   $html [description]
     *
     * @return [type] [description]
     */
    public function redirect($url, $time = 0, $rewrite = true)
    {
        if (empty($url)) {
            $url = 'index.php';
        }
        if ($rewrite) {
            $url = $this->link($url);
        }

        $ajax = $this->get('is_ajax_request');
        if ($ajax) {
            $status = $this->release('status');

            $status['redirect'] = $url;
            $this->assign('status', $status);
            $this->assign('redirect', $url);
            $this->set('redirect', $url);

            return true;
        }

        if (headers_sent()) {
            echo  '<meta http-equiv="refresh" content="'.$time.'; url='.$url.'"/>';

            return true;
        }

        if ($time) {
            header('refresh:'.$time.';url='.str_replace('&amp;', '&', $url));
        } else {
            header('location:'.str_replace('&amp;', '&', $url));
        }
    }

    public function link($url)
    {
        return Router::link($url);
    }
}
